import { Head, router, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    FileText,
    KeyRound,
    ListFilter,
    LogOut,
    Power,
    Search,
    Send,
    SquarePen,
    Trash2,
    Upload,
    Users,
    Plus,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { CsvImportSheet } from '@/components/csv-actions';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OccupantActionsMenu } from '@/components/occupant-actions-menu';
import OccupantFormFields from '@/components/occupant-form-fields';
import { OverlayFooter } from '@/components/overlay-footer';
import PasswordInput from '@/components/password-input';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import type { TemplateToken } from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
import {
    index as leasesIndex,
    remind as remindLease,
} from '@/routes/tenant/leases';
import {
    agreement as occupantAgreement,
    destroy as destroyOccupant,
    index as indexRoute,
    moveIn as moveInOccupant,
    moveOut as moveOutOccupant,
    show as showOccupant,
    store as storeOccupant,
    update as updateOccupant,
    importMethod as importOccupants,
} from '@/routes/tenant/occupants';
import { index as propertiesIndex } from '@/routes/tenant/properties';
import { bulk as trashBulk } from '@/routes/tenant/trash';

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
    rent_amount: string | null;
    rent_frequency: string | null;
};

type Lease = {
    starts_at: string | null;
    rent_amount: string | null;
    rent_frequency: string | null;
    rent_due_day: number | null;
    deposit: string | null;
    currency: string | null;
    agreement_text: string | null;
    agreement_document_name: string | null;
    agreement_document_url: string | null;
};

type OccupantLease = {
    id: number;
    unit_id: number | null;
    unit_name: string | null;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    rent_amount: string | null;
    currency: string | null;
    outstanding: string;
    is_overdue: boolean;
    has_agreement: boolean;
    agreement_document_name: string | null;
    agreement_document_url: string | null;
};

/** Row-level one-tap action: a single overdue lease and its balance. */
type Attention = {
    lease_id: number;
    outstanding: string;
    currency: string | null;
};

type PendingLease = {
    lease_id: number;
    unit_id: number | null;
    unit_name: string | null;
    rent_amount: string | null;
    currency: string | null;
    deposit: string | null;
};

type HistoryLine = {
    date: string | null;
    kind: string;
    title: string;
    detail: string | null;
    amount: string | null;
    currency: string | null;
    status: string | null;
    unit_id: number | null;
    unit_name: string | null;
};

type Occupant = {
    id: number;
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    national_id: string | null;
    status: string;
    units: Unit[];
    deletable: boolean;
    /** Row-level overflow offers the agreement without a second request. */
    has_agreement?: boolean;
    /** Row-level one-tap remind; null unless exactly one lease is overdue. */
    attention?: Attention | null;
    /** The row's live tenancy for the overflow's End lease deep link. */
    active_lease_id?: number | null;
    // Present on the record resolved for the view sheet. List rows omit them
    // because the card does not show them, and the sheet is the only reader.
    gender?: string | null;
    added_at?: string | null;
    lease?: Lease | null;
    /** Every tenancy for the sheet switcher; focused record only. */
    leases?: OccupantLease[] | null;
    /** All of the person's units in the property, across rows. */
    all_unit_ids?: number[];
    /** Chronological story, newest first; focused record only. */
    history?: HistoryLine[] | null;
    /** Whatever carries the printable agreement (active or ended lease). */
    agreement_lease?: Lease | null;
    pending_leases?: PendingLease[] | null;
};

type AgreementTemplateOption = {
    id: number;
    name: string;
    body_html: string;
};

type Paginator = {
    data: Occupant[];
    current_page: number;
    last_page: number;
    total: number;
};

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'moved_out', label: 'Moved out' },
];

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

type QuickUnit = {
    id: number;
    name: string;
};

/**
 * The sub-minute tenant capture: name, phone, unit, rent. Everything else
 * (email, agreement, extras) stays in the full form. Posts to the same
 * store endpoint with the lease pre-filled: monthly, property currency,
 * moved in now.
 */
function QuickAddFields({
    units,
    currency,
    errors,
}: {
    units: QuickUnit[];
    currency: string;
    errors: Record<string, string>;
}) {
    const [name, setName] = useState('');
    const [unitId, setUnitId] = useState('');
    const [rent, setRent] = useState('');

    const valid = name.trim() !== '' && unitId !== '' && Number(rent) > 0;

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="quick-first-name">First name</Label>
                <Input
                    id="quick-first-name"
                    name="first_name"
                    type="text"
                    required
                    autoComplete="off"
                    placeholder="John"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
                <InputError message={errors.first_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="quick-phone">Phone (optional)</Label>
                <Input
                    id="quick-phone"
                    name="phone"
                    type="tel"
                    autoComplete="off"
                    placeholder="0712 000 000"
                />
                <InputError message={errors.phone} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="quick-unit">Unit</Label>
                <input type="hidden" name="unit_ids[]" value={unitId} />
                <input type="hidden" name="status" value="active" />
                <input type="hidden" name="move_in_now" value="1" />
                <input
                    type="hidden"
                    name="lease[rent_frequency]"
                    value="monthly"
                />
                <input type="hidden" name="lease[currency]" value={currency} />
                <Select value={unitId} onValueChange={setUnitId}>
                    <SelectTrigger id="quick-unit">
                        <SelectValue placeholder="Choose a unit" />
                    </SelectTrigger>
                    <SelectContent>
                        {units.map((unit) => (
                            <SelectItem key={unit.id} value={String(unit.id)}>
                                {unit.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.unit_ids} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="quick-rent">Monthly rent ({currency})</Label>
                <Input
                    id="quick-rent"
                    name="lease[rent_amount]"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    placeholder="5000"
                    value={rent}
                    onChange={(event) => setRent(event.target.value)}
                />
                <InputError message={errors['lease.rent_amount']} />
            </div>
            {!valid && (
                <p className="text-sm text-muted-foreground" role="note">
                    Fill name, unit and rent to add the tenant — email and the
                    agreement can wait.
                </p>
            )}
        </div>
    );
}

/**
 * One occupant's tenancies with the actions each one needs: remind the
 * overdue one where it stands, jump to the leases page for amend, charge
 * and end, download that lease's own agreement document. The sheet picks
 * the tenancy (tabs) so multi-unit occupants stop being ambiguous, and
 * the parent keys this by occupant so the pick never leaks across rows.
 */
function OccupantLeasesSection({
    leases,
    currency,
    canRemind,
    canAmendRent,
    canManageExtras,
    canEndLease,
    onRemind,
    onOpenLease,
}: {
    leases: OccupantLease[];
    currency: string;
    canRemind: boolean;
    canAmendRent: boolean;
    canManageExtras: boolean;
    canEndLease: boolean;
    onRemind: (leaseId: number) => void;
    onOpenLease: (leaseId: number, extra?: Record<string, string>) => void;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const selected =
        leases.find((lease) => lease.id === selectedId) ??
        leases.find((lease) => lease.status === 'active') ??
        leases[0] ??
        null;

    if (!selected) {
        return (
            <p className="text-sm text-muted-foreground">
                No tenancies in this property.
            </p>
        );
    }

    return (
        <div className="space-y-4">
            {leases.length > 1 && (
                <div className="flex flex-wrap gap-2">
                    {leases.map((lease) => (
                        <Button
                            key={lease.id}
                            type="button"
                            size="sm"
                            variant={
                                lease.id === selected.id ? 'default' : 'outline'
                            }
                            onClick={() => setSelectedId(lease.id)}
                        >
                            {lease.unit_name ?? 'No unit'} · {lease.status}
                        </Button>
                    ))}
                </div>
            )}

            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                {(
                    [
                        [
                            'Period',
                            `${selected.starts_at ?? '—'} → ${selected.ends_at ?? 'open'}`,
                        ],
                        [
                            'Rent',
                            formatMoney(
                                selected.rent_amount,
                                selected.currency ?? currency,
                            ),
                        ],
                        [
                            'Outstanding',
                            selected.is_overdue
                                ? formatMoney(
                                      selected.outstanding,
                                      selected.currency ?? currency,
                                  )
                                : 'Paid up',
                        ],
                    ] as [string, string][]
                ).map(([label, value]) => (
                    <div
                        key={label}
                        className="grid grid-cols-[8rem_1fr] gap-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd
                            className={`min-w-0 break-words tabular-nums ${label === 'Outstanding' && selected.is_overdue ? 'font-semibold text-red-600 dark:text-red-400' : ''}`}
                        >
                            {value}
                        </dd>
                    </div>
                ))}

                <div className="grid grid-cols-[8rem_1fr] gap-2">
                    <dt className="text-muted-foreground">Agreement</dt>
                    <dd className="min-w-0 break-words">
                        {selected.agreement_document_url ? (
                            <a
                                href={selected.agreement_document_url}
                                className="underline underline-offset-4"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {selected.agreement_document_name ?? 'Download'}
                            </a>
                        ) : (
                            'None uploaded'
                        )}
                    </dd>
                </div>
            </dl>

            <div className="flex flex-wrap gap-2">
                {canRemind && selected.is_overdue && (
                    <Button
                        type="button"
                        size="sm"
                        className="bg-brand-accent text-on-accent hover:bg-brand-accent/90"
                        onClick={() => onRemind(selected.id)}
                    >
                        <Send className="size-4" />
                        Remind{' '}
                        {formatMoney(selected.outstanding, selected.currency)}
                    </Button>
                )}
                {canManageExtras && selected.status === 'active' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            onOpenLease(selected.id, { extra: 'new' })
                        }
                    >
                        <Plus className="size-4" />
                        Add charge
                    </Button>
                )}
                {canAmendRent && selected.status === 'active' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => onOpenLease(selected.id, { amend: '1' })}
                    >
                        <SquarePen className="size-4" />
                        Amend rent
                    </Button>
                )}
                {canEndLease && selected.status === 'active' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                        onClick={() =>
                            onOpenLease(selected.id, {
                                dialog: 'end-lease',
                            })
                        }
                    >
                        <Power className="size-4" />
                        End lease
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * The occupant's story, newest first, filterable to one tenancy. The
 * filter defaults to the opened row's unit (that is the story the row's
 * History button promises) with an all-units escape hatch. Keyed by
 * occupant by the caller so the pick never leaks across rows.
 */
function OccupantHistorySection({
    events,
    rowUnitId,
}: {
    events: HistoryLine[];
    rowUnitId: number | null;
}) {
    const units = useMemo(() => {
        const seen = new Map<number, string>();

        for (const event of events) {
            if (event.unit_id !== null && !seen.has(event.unit_id)) {
                seen.set(
                    event.unit_id,
                    event.unit_name ?? `Unit ${event.unit_id}`,
                );
            }
        }

        return [...seen.entries()].map(([id, name]) => ({ id, name }));
    }, [events]);

    const [unitFilter, setUnitFilter] = useState<number | 'all'>(
        rowUnitId !== null && units.some((unit) => unit.id === rowUnitId)
            ? rowUnitId
            : 'all',
    );

    const visible =
        unitFilter === 'all'
            ? events
            : events.filter((event) => event.unit_id === unitFilter);

    return (
        <div className="mt-6 space-y-2" id="occupant-history">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-medium">History</h3>
                {units.length > 1 && (
                    <div className="flex flex-wrap gap-1">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                unitFilter === 'all' ? 'default' : 'outline'
                            }
                            className="h-7 text-xs"
                            onClick={() => setUnitFilter('all')}
                        >
                            All units
                        </Button>
                        {units.map((unit) => (
                            <Button
                                key={unit.id}
                                type="button"
                                size="sm"
                                variant={
                                    unitFilter === unit.id
                                        ? 'default'
                                        : 'outline'
                                }
                                className="h-7 text-xs"
                                onClick={() => setUnitFilter(unit.id)}
                            >
                                {unit.name}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
            {visible.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {unitFilter === 'all'
                        ? 'Nothing recorded yet — tenancies, payments, refunds and notices will appear here.'
                        : `Nothing recorded for ${units.find((unit) => unit.id === unitFilter)?.name ?? 'this unit'} yet.`}
                </p>
            ) : (
                <ul className="space-y-3 py-2 text-sm">
                    {visible.map((event, index) => (
                        <li
                            key={`${event.kind}-${event.date ?? 'undated'}-${index}`}
                            className="grid grid-cols-[6.5rem_1fr] gap-2"
                        >
                            <span className="text-muted-foreground tabular-nums">
                                {event.date ?? '—'}
                            </span>
                            <span className="min-w-0">
                                <span className="font-medium">
                                    {event.title}
                                </span>
                                {event.detail && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {event.detail}
                                    </span>
                                )}
                                {event.amount !== null && (
                                    <span className="ml-2 font-medium tabular-nums">
                                        {event.currency
                                            ? `${event.currency} `
                                            : ''}
                                        {event.amount}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

type Props = {
    property: Property;
    occupants: Paginator;
    focusedOccupant: Occupant | null;
    currency: string;
    units?: Unit[];
    templates?: {
        property: AgreementTemplateOption[];
        organization: AgreementTemplateOption[];
    };
    availableTokens?: TemplateToken[];
};

export default function OccupantsIndex({
    property,
    occupants,
    focusedOccupant,
    currency,
    units,
    templates,
    availableTokens,
}: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canCreate = permissions.includes('occupant.create');
    const canEdit = permissions.includes('occupant.edit');
    const canDelete = permissions.includes('occupant.delete');
    // Lease workbench gates, mirrored from the leases page: remind, amend,
    // extras and end keep their own permission checks here too.
    const canRemind = permissions.includes('lease.manage');
    const canAmendRent = permissions.includes('lease.manage');
    const canManageExtras = permissions.includes('lease.manage');
    const canEndLease = permissions.includes('lease.delete');

    function sendLeaseReminder(leaseId: number) {
        router.post(
            remindLease({ property: property.slug, lease: leaseId }).url,
            {},
            { preserveScroll: true },
        );
    }

    function openLeaseOnLeasesPage(
        leaseId: number,
        extra: Record<string, string> = {},
    ) {
        router.visit(
            leasesIndex(property.slug, {
                query: {
                    mode: 'view',
                    lease: String(leaseId),
                    ...extra,
                },
            }).url,
        );
    }

    /** Scroll the open sheet to its history timeline. */
    function scrollToHistory(delay = 300) {
        window.setTimeout(() => {
            document
                .querySelector('#occupant-history')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, delay);
    }

    /**
     * Row overflow entry to the occupant's story, opened on the sheet and
     * scrolled straight to the history (pre-filtered to the row's unit).
     * Overlays never stack, so the menu closes first — same rule as the
     * toolbar overflow.
     */
    function openHistorySheet(occupant: Occupant) {
        setOverlayParam('mode', 'view', {
            only: ['occupants', 'focusedOccupant'],
            keep: { occupant: String(occupant.id) },
        });

        scrollToHistory(700);
    }

    const [bulkOpen, setBulkOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [moveInMethod, setMoveInMethod] = useState('cash');
    // Live-validated by the form sheets below; the footer submit stays
    // disabled until the visible fields pass. Reset on close so opening a
    // different row never inherits the previous sheet's verdict.
    const [createValid, setCreateValid] = useState(true);
    const [editValid, setEditValid] = useState(true);

    const { query, setOverlayParam } = useOverlayParam();

    // Selection never outlives the page: a refetch that drops a row also
    // drops its tick, so the bulk payload can only name visible rows.
    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                occupants.data.some((occupant) => occupant.id === id),
            ),
        );
    }, [occupants.data]);

    const filters = {
        search: query.get('search') ?? '',
        status: query.get('status') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    const isFiltered = filters.search !== '' || filters.status !== '';

    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    const [isTableLoading, setIsTableLoading] = useState(false);

    const only = ['occupants'];

    function navigateTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(indexRoute({ property: property.slug }).url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
        });
    }

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(indexRoute({ property: property.slug }).url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
            onFinish: () => setIsTableLoading(false),
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                sort: first?.id ?? '',
                direction: first
                    ? first.desc
                        ? 'desc'
                        : 'asc'
                    : sort.direction,
            },
            { replace: true },
        );
    }

    function changePage(nextPage: number) {
        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                sort: sort.column ?? '',
                direction: sort.direction,
                page: nextPage,
            },
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === filters.search) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetchTable(
                {
                    search: searchDraft,
                    status: filters.status,
                    sort: sort.column ?? '',
                    direction: sort.direction,
                },
                { replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    // The delete modal builds its DELETE URL from the row, so the id travels in
    // the URL beside the mode rather than in component state.
    const deleteTargetId = Number(query.get('occupant'));
    const deleteTarget = occupants.data.find(
        (occupant) => occupant.id === deleteTargetId,
    );
    const isDeleteOpen = query.get('mode') === 'delete' && !!deleteTarget;

    // The record is a sheet on this page, so the id travels in the URL and the
    // server resolves it into `focusedOccupant`. Falling back to the visible
    // rows keeps opening from the list a single request.
    const viewedOccupantId = Number(query.get('occupant'));
    const isViewOpen = query.get('mode') === 'view' && viewedOccupantId > 0;
    const viewedOccupant = isViewOpen
        ? (focusedOccupant ??
          occupants.data.find((occupant) => occupant.id === viewedOccupantId) ??
          null)
        : null;

    // The sheet menu acts on the viewed tenancy: the active lease when
    // there is one, mirroring the row's active_lease_id without another
    // request.
    const viewedActiveLeaseId =
        viewedOccupant?.leases?.find((lease) => lease.status === 'active')
            ?.id ??
        viewedOccupant?.active_lease_id ??
        null;

    const isCreateOpen = query.get('create') === '1';
    const isQuickOpen = query.get('quick') === '1';
    const isImportOpen = query.get('import') === '1';

    // Deep links from the Needs attention panel (?create=1&unit=5) arrive
    // with the unit to fill. Validated against this property's units so a
    // stray id selects nothing rather than the wrong unit.
    const preselectedUnitId = Number(query.get('unit'));
    const preselectedUnit =
        Number.isFinite(preselectedUnitId) &&
        preselectedUnitId > 0 &&
        (units ?? []).some((unit) => unit.id === preselectedUnitId)
            ? preselectedUnitId
            : null;

    // Edit resolves like view; the sheet needs the full lease, which only
    // the server-fetched record carries.
    const isEditOpen =
        query.get('mode') === 'edit' &&
        viewedOccupantId > 0 &&
        focusedOccupant?.id === viewedOccupantId;

    const isMoveOutOpen =
        query.get('mode') === 'move-out' && viewedOccupantId > 0;
    const moveOutTarget =
        isMoveOutOpen &&
        (focusedOccupant?.id === viewedOccupantId
            ? focusedOccupant
            : (occupants.data.find(
                  (occupant) => occupant.id === viewedOccupantId,
              ) ?? null));

    // Move-in needs the pending leases, which only the server-fetched
    // record carries — a row fallback would open a sheet with nothing to
    // collect.
    const isMoveInOpen =
        query.get('mode') === 'move-in' &&
        viewedOccupantId > 0 &&
        focusedOccupant?.id === viewedOccupantId;
    const moveInTarget = isMoveInOpen ? focusedOccupant : null;

    const formOptionsLoaded =
        units !== undefined &&
        templates !== undefined &&
        availableTokens !== undefined;

    // Direct links (?create=1, ?quick=1, shared URLs) open the sheet
    // without a button click, so the options also load on mount when a
    // form sheet is open. Quick add needs units only — the template
    // library and token list stay unloaded.
    useEffect(() => {
        if (isQuickOpen && units === undefined) {
            router.reload({ only: ['units'] });

            return;
        }

        if ((isCreateOpen || isEditOpen) && !formOptionsLoaded) {
            router.reload({
                only: ['units', 'templates', 'availableTokens'],
            });
        }
    }, [isCreateOpen, isEditOpen, isQuickOpen, formOptionsLoaded, units]);

    // The unit list, template library and tokens are heavy enough to skip
    // until a form sheet actually opens.
    function ensureFormOptions() {
        if (!formOptionsLoaded) {
            router.reload({
                only: ['units', 'templates', 'availableTokens'],
            });
        }
    }

    /** Remembers which occupant form this person prefers, so the mode
     * default only decides the first visit and the human decides after
     * that. Missing or unreadable storage behaves like no preference. */
    const FORM_PREF_KEY = 'occupant.form.default';

    function readFormPref(): 'quick' | 'full' | null {
        try {
            const raw = localStorage.getItem(FORM_PREF_KEY);

            return raw === 'quick' || raw === 'full' ? raw : null;
        } catch {
            return null;
        }
    }

    function writeFormPref(value: 'quick' | 'full') {
        try {
            localStorage.setItem(FORM_PREF_KEY, value);
        } catch {
            // Private browsing etc: the preference just does not stick.
        }
    }

    function openCreate() {
        ensureFormOptions();
        setOverlayParam('create', '1', {
            only: ['occupants', 'units', 'templates', 'availableTokens'],
        });
    }

    function openQuickAdd() {
        // The mount effect below fetches units when the sheet opens; the
        // full form's option list is untouched.
        setOverlayParam('quick', '1', {
            only: ['occupants', 'units'],
        });
    }

    /**
     * One entry point for adding an occupant. The organization's mode picks
     * the starting sheet — quick for simple orgs, full for pro ones — but
     * an explicit switch (either direction) becomes this person's default.
     */
    function openAddOccupant() {
        const preference = readFormPref();

        if (preference === 'full') {
            openCreate();

            return;
        }

        if (preference === 'quick') {
            openQuickAdd();

            return;
        }

        if (context?.organization?.mode === 'pro') {
            openCreate();
        } else {
            openQuickAdd();
        }
    }

    function switchToQuickAdd() {
        writeFormPref('quick');
        setOverlayParam('quick', '1', {
            replace: true,
            only: ['occupants', 'units'],
            keep: { create: '' },
        });
    }

    function switchToFullForm() {
        writeFormPref('full');
        setOverlayParam('create', '1', {
            replace: true,
            only: ['occupants', 'units', 'templates', 'availableTokens'],
            keep: { quick: '' },
        });
    }

    function openEdit(occupant: Occupant) {
        ensureFormOptions();
        setOverlayParam('mode', 'edit', {
            only: ['occupants', 'focusedOccupant'],
            keep: { occupant: String(occupant.id) },
        });
    }

    function openMoveOut(occupant: Occupant) {
        setOverlayParam('mode', 'move-out', {
            only: ['occupants', 'focusedOccupant'],
            keep: { occupant: String(occupant.id) },
        });
    }

    function openMoveIn(occupant: Occupant) {
        setMoveInMethod('cash');
        setOverlayParam('mode', 'move-in', {
            only: ['occupants', 'focusedOccupant'],
            keep: { occupant: String(occupant.id) },
        });
    }

    function closeMoveIn() {
        setOverlayParam('mode', 'view', {
            replace: true,
            only: ['occupants', 'focusedOccupant'],
            keep: { occupant: String(viewedOccupantId) },
        });
    }

    function closeSheets() {
        setCreateValid(true);
        setEditValid(true);
        setOverlayParam('mode', null, {
            replace: true,
            only: ['occupants'],
            keep: { occupant: '', create: '' },
        });
    }

    function closeView() {
        setOverlayParam('mode', null, {
            replace: true,
            only: ['occupants'],
            keep: { occupant: '' },
        });
    }

    const columns = useMemo<ColumnDef<Occupant, unknown>[]>(
        () => [
            {
                id: 'name',
                header: 'Occupant',
                cell: ({ row }) => (
                    <div className="min-w-0 truncate font-medium">
                        {row.original.first_name} {row.original.last_name ?? ''}
                    </div>
                ),
            },
            {
                id: 'contact',
                header: 'Contact',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <div className="truncate text-xs">
                            {row.original.email}
                        </div>
                        {row.original.phone && (
                            <div className="truncate text-xs text-muted-foreground">
                                {row.original.phone}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                id: 'units',
                header: 'Units',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.units.length === 0 ? (
                        <span className="text-xs text-muted-foreground">
                            No units assigned
                        </span>
                    ) : (
                        <div className="flex flex-wrap gap-1">
                            {row.original.units.map((unit) => (
                                <Badge
                                    key={unit.id}
                                    variant="outline"
                                    className="border-transparent bg-brand-accent text-on-accent hover:bg-brand-accent"
                                >
                                    {unit.name}
                                </Badge>
                            ))}
                        </div>
                    ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.status === 'active'
                                ? 'success'
                                : row.original.status === 'moved_out'
                                  ? 'danger'
                                  : 'warning'
                        }
                        className="text-xs capitalize"
                    >
                        {row.original.status?.replace('_', ' ') ?? '—'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const occupant = row.original;
                    const name =
                        `${occupant.first_name} ${occupant.last_name ?? ''}`.trim();
                    const attention = occupant.attention;

                    return (
                        <div
                            className="flex justify-end gap-1"
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canRemind && attention && (
                                <ActionIconButton
                                    icon={<Send />}
                                    label={`Remind ${name} — ${formatMoney(attention.outstanding, attention.currency)} outstanding`}
                                    color="teal"
                                    onClick={() =>
                                        sendLeaseReminder(attention.lease_id)
                                    }
                                />
                            )}
                            {canEdit && occupant.status !== 'moved_out' && (
                                <ActionIconButton
                                    icon={<LogOut />}
                                    label={`Move out ${name}`}
                                    color="teal"
                                    onClick={() => openMoveOut(occupant)}
                                />
                            )}
                            {canEdit && (
                                <ActionIconButton
                                    icon={<SquarePen />}
                                    label={`Edit ${name}`}
                                    color="edit"
                                    onClick={() => openEdit(occupant)}
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${name}`}
                                    color="danger"
                                    disabledReason={
                                        !occupant.deletable
                                            ? 'Only its creator or the owner can delete this occupant.'
                                            : null
                                    }
                                    onClick={() =>
                                        setOverlayParam('mode', 'delete', {
                                            only: ['occupants'],
                                            keep: {
                                                occupant: String(occupant.id),
                                            },
                                        })
                                    }
                                />
                            )}
                            <OccupantActionsMenu
                                label={`More actions for ${name}`}
                                showViewAgreement={!!occupant.has_agreement}
                                showAmend={
                                    canAmendRent &&
                                    occupant.active_lease_id != null
                                }
                                showAddCharge={
                                    canManageExtras &&
                                    occupant.active_lease_id != null
                                }
                                showEndLease={
                                    canEndLease &&
                                    occupant.active_lease_id != null
                                }
                                onHistory={() => openHistorySheet(occupant)}
                                onViewAgreement={() =>
                                    router.visit(
                                        occupantAgreement({
                                            property: property.slug,
                                            occupant: occupant.id,
                                        }).url,
                                    )
                                }
                                onAmend={() =>
                                    openLeaseOnLeasesPage(
                                        occupant.active_lease_id as number,
                                        { amend: '1' },
                                    )
                                }
                                onAddCharge={() =>
                                    openLeaseOnLeasesPage(
                                        occupant.active_lease_id as number,
                                        { extra: 'new' },
                                    )
                                }
                                onEndLease={() =>
                                    openLeaseOnLeasesPage(
                                        occupant.active_lease_id as number,
                                        {
                                            dialog: 'end-lease',
                                        },
                                    )
                                }
                            />
                        </div>
                    );
                },
            },
        ],
        // Stable per page: the property never changes under this table, so
        // the row actions intentionally close over it (same as openEdit).
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [canEdit, canDelete],
    );

    function closeQuickAdd() {
        setOverlayParam('quick', null, {
            replace: true,
            only: ['occupants'],
            keep: { occupant: '', create: '', quick: '' },
        });
    }

    return (
        <>
            <Head title={`${property.name} occupants`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        variant="small"
                        title={`${property.name} occupants`}
                        description="The people renting units in this property."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Add occupant"
                            tooltip="Add an occupant to this property"
                            color="primary"
                            onClick={openAddOccupant}
                            className="ml-auto max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search name, email or unit..."
                                aria-label="Search occupants"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        {/* A lone utility button stays visible at every width —
                         * the overflow drawer only earns its place once a row
                         * of icons would wrap. */}
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            className="ml-auto coarse:min-h-9 coarse:min-w-9"
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', '')
                                    : setOverlayParam('filters', '1')
                            }
                        />
                        {canCreate && (
                            <ActionIconButton
                                icon={<Upload />}
                                label="Import occupants"
                                color="teal"
                                className="coarse:min-h-9 coarse:min-w-9"
                                onClick={() => setOverlayParam('import', '1')}
                            />
                        )}
                    </div>

                    <BulkDeleteBar
                        pageIds={occupants.data.map((occupant) => occupant.id)}
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                        onDelete={() => setBulkOpen(true)}
                        onClear={() => setSelectedIds([])}
                        canDelete={canDelete}
                        noun="occupants"
                    />

                    <DataTable
                        columns={columns}
                        data={occupants.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        selectable={canDelete}
                        selectedIds={selectedIds.map(String)}
                        onSelectionChange={(ids) =>
                            setSelectedIds(ids.map(Number))
                        }
                        selectionLabel={(occupant) =>
                            `Select occupant ${occupant.first_name} ${occupant.last_name ?? ''}`.trim()
                        }
                        onRowClick={(occupant) =>
                            router.get(
                                showOccupant({
                                    property: property.slug,
                                    occupant: occupant.id,
                                }).url,
                            )
                        }
                        empty={
                            isFiltered ? (
                                'No occupants match your filters.'
                            ) : (
                                <Empty
                                    icon={
                                        <Users className="mx-auto size-10 text-muted-foreground" />
                                    }
                                    title="No occupants yet"
                                    description="Add your first occupant to assign them to a unit."
                                    action={
                                        canCreate ? (
                                            <Button
                                                type="button"
                                                className="mt-6"
                                                onClick={openAddOccupant}
                                            >
                                                Add your first occupant
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            )
                        }
                    />
                </Card>

                <Pagination
                    currentPage={occupants.current_page}
                    lastPage={occupants.last_page}
                    total={occupants.total}
                    label="occupants"
                    onChange={changePage}
                />

                <ResponsiveOverlay
                    open={isFiltersOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            setOverlayParam('filters', '');
                        }
                    }}
                    title="Filters"
                    description="Narrow the list of occupants."
                    footer={
                        <OverlayFooter
                            onCancel={() => setOverlayParam('filters', '')}
                            cancelLabel="Close"
                            actions={[
                                {
                                    label: 'Clear all',
                                    onClick: () => {
                                        setSearchDraft('');
                                        refetchTable(
                                            {
                                                search: '',
                                                status: '',
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                            },
                                            { replace: true },
                                        );
                                        setOverlayParam('filters', '');
                                    },
                                },
                            ]}
                        />
                    }
                >
                    <div className="grid gap-4 py-2">
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">
                                Status
                            </legend>
                            <RadioGroup
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    refetchTable(
                                        {
                                            search: filters.search,
                                            status:
                                                value === 'all' ? '' : value,
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                            filters: '1',
                                        },
                                        { replace: true },
                                    )
                                }
                                className="space-y-3"
                            >
                                {[
                                    { value: 'all', label: 'Any status' },
                                    ...STATUSES,
                                ].map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center gap-2"
                                    >
                                        <RadioGroupItem
                                            id={`occupant-status-${option.value}`}
                                            value={option.value}
                                        />
                                        <Label
                                            htmlFor={`occupant-status-${option.value}`}
                                            className="cursor-pointer"
                                        >
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </fieldset>
                    </div>
                </ResponsiveOverlay>
            </div>

            {viewedOccupant && (
                <ResponsiveOverlay
                    open={isViewOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeView();
                        }
                    }}
                    title={`${viewedOccupant.first_name} ${viewedOccupant.last_name ?? ''}`.trim()}
                    description={`Occupant of ${property.name}.`}
                    actions={
                        <>
                            {viewedOccupant.agreement_lease &&
                                (viewedOccupant.agreement_lease
                                    .agreement_text ||
                                    viewedOccupant.agreement_lease
                                        .agreement_document_url) && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() =>
                                            router.visit(
                                                occupantAgreement({
                                                    property: property.slug,
                                                    occupant: viewedOccupant.id,
                                                }).url,
                                            )
                                        }
                                    >
                                        <FileText className="size-4" />
                                        Agreement
                                    </Button>
                                )}
                            {canEdit &&
                                viewedOccupant.status === 'inactive' &&
                                (viewedOccupant.pending_leases?.length ?? 0) >
                                    0 && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() =>
                                            openMoveIn(viewedOccupant)
                                        }
                                    >
                                        <KeyRound className="size-4" />
                                        Move in
                                    </Button>
                                )}
                            {canEdit && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="bg-action-edit-fill text-white hover:bg-action-edit-fill hover:brightness-95"
                                    onClick={() => {
                                        ensureFormOptions();
                                        setOverlayParam('mode', 'edit', {
                                            only: [
                                                'occupants',
                                                'focusedOccupant',
                                            ],
                                            keep: {
                                                occupant: String(
                                                    viewedOccupant.id,
                                                ),
                                            },
                                        });
                                    }}
                                >
                                    <SquarePen className="size-4" />
                                    Edit
                                </Button>
                            )}
                            <OccupantActionsMenu
                                label={`More actions for ${viewedOccupant.first_name ?? 'occupant'}`}
                                showViewAgreement={Boolean(
                                    viewedOccupant.agreement_lease,
                                )}
                                showAmend={
                                    canAmendRent && viewedActiveLeaseId != null
                                }
                                showAddCharge={
                                    canManageExtras &&
                                    viewedActiveLeaseId != null
                                }
                                showEndLease={
                                    canEndLease && viewedActiveLeaseId != null
                                }
                                onHistory={() => scrollToHistory()}
                                onViewAgreement={() =>
                                    router.visit(
                                        occupantAgreement({
                                            property: property.slug,
                                            occupant: viewedOccupant.id,
                                        }).url,
                                    )
                                }
                                onAmend={() =>
                                    openLeaseOnLeasesPage(
                                        viewedActiveLeaseId as number,
                                        { amend: '1' },
                                    )
                                }
                                onAddCharge={() =>
                                    openLeaseOnLeasesPage(
                                        viewedActiveLeaseId as number,
                                        { extra: 'new' },
                                    )
                                }
                                onEndLease={() =>
                                    openLeaseOnLeasesPage(
                                        viewedActiveLeaseId as number,
                                        {
                                            dialog: 'end-lease',
                                        },
                                    )
                                }
                            />
                        </>
                    }
                    footer={<OverlayFooter onCancel={closeView} />}
                >
                    <dl className="grid gap-x-6 gap-y-3 py-2 text-sm sm:grid-cols-2">
                        {(
                            [
                                ['Email', viewedOccupant.email ?? '—'],
                                ['Phone', viewedOccupant.phone ?? '—'],
                                [
                                    'National ID',
                                    viewedOccupant.national_id ?? '—',
                                ],
                                ['Gender', viewedOccupant.gender ?? '—'],
                                ['Added', viewedOccupant.added_at ?? '—'],
                            ] as [string, string][]
                        ).map(([label, value]) => (
                            <div
                                key={label}
                                className="grid grid-cols-[8rem_1fr] gap-2"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="min-w-0 break-words">
                                    {value}
                                    {label === 'Email' &&
                                        viewedOccupant.email === null &&
                                        canEdit && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                No email on file — edit to add
                                                one for portal access and
                                                reminders.
                                            </span>
                                        )}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <div className="mt-6 space-y-2">
                        <h3 className="text-sm font-medium">
                            Units ({viewedOccupant.units.length})
                        </h3>
                        {viewedOccupant.units.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No units assigned.
                            </p>
                        ) : (
                            <ul className="divide-y divide-white/20 rounded-lg bg-brand-accent text-on-accent">
                                {viewedOccupant.units.map((unit) => (
                                    <li
                                        key={unit.id}
                                        className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                                    >
                                        <span className="font-medium">
                                            {unit.name}
                                        </span>
                                        <span className="text-on-accent/70">
                                            {[
                                                unit.type,
                                                unit.rent_amount !== null
                                                    ? `${currency} ${unit.rent_amount}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={
                                                unit.status === 'occupied'
                                                    ? 'border-transparent bg-white text-brand-accent hover:bg-white'
                                                    : 'border-white/30 text-on-accent hover:bg-transparent'
                                            }
                                        >
                                            {unit.status}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="mt-6 space-y-2">
                        <h3 className="text-sm font-medium">
                            Leases ({(viewedOccupant.leases ?? []).length})
                        </h3>
                        <OccupantLeasesSection
                            key={viewedOccupant.id}
                            leases={viewedOccupant.leases ?? []}
                            currency={currency}
                            canRemind={canRemind}
                            canAmendRent={canAmendRent}
                            canManageExtras={canManageExtras}
                            canEndLease={canEndLease}
                            onRemind={sendLeaseReminder}
                            onOpenLease={openLeaseOnLeasesPage}
                        />
                    </div>

                    <OccupantHistorySection
                        key={`history-${viewedOccupant.id}`}
                        events={viewedOccupant.history ?? []}
                        rowUnitId={viewedOccupant.units[0]?.id ?? null}
                    />
                </ResponsiveOverlay>
            )}

            {canDelete && deleteTarget && (
                <ConfirmDeleteOverlay
                    open={isDeleteOpen}
                    onOpenChange={(open) =>
                        !open &&
                        setOverlayParam('mode', null, {
                            replace: true,
                            only: ['occupants'],
                            keep: { occupant: '' },
                        })
                    }
                    action={
                        destroyOccupant({
                            property: property.slug,
                            occupant: deleteTarget.id,
                        }).url
                    }
                    title={`Remove ${deleteTarget.first_name} ${deleteTarget.last_name ?? ''}`.trim()}
                    description="Their units are released and the occupant record is removed. Confirm with your password — this cannot be undone from the interface."
                />
            )}

            {canDelete && bulkOpen && selectedIds.length > 0 && (
                <ConfirmDeleteOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setBulkOpen(false);
                        }
                    }}
                    action={trashBulk().url}
                    payload={{ module: 'occupants', ids: selectedIds }}
                    title={
                        selectedIds.length === 1
                            ? 'Delete this occupant?'
                            : `Delete ${selectedIds.length} occupants?`
                    }
                    description="They move to trash, where they can be restored or permanently deleted."
                    submitLabel={
                        selectedIds.length === 1
                            ? 'Delete occupant'
                            : `Delete ${selectedIds.length} occupants`
                    }
                    processingLabel="Deleting…"
                    onSuccess={() => {
                        setSelectedIds([]);
                        setBulkOpen(false);
                    }}
                />
            )}

            {canCreate && (
                <ResponsiveOverlay
                    open={isQuickOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeQuickAdd();
                        }
                    }}
                    title="Quick add tenant"
                    description={`Record ${property.name}'s new tenant in under a minute. Email and agreements can wait.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeQuickAdd}
                            actions={[
                                {
                                    label: 'Add tenant',
                                    form: 'occupant-quick-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    }
                >
                    <button
                        type="button"
                        onClick={switchToFullForm}
                        className="mb-4 text-sm text-accent-text underline underline-offset-4 hover:text-accent-text-hover"
                    >
                        Need the full form? Agreements, extras, templates.
                    </button>
                    {units === undefined ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : (
                        <Form
                            id="occupant-quick-form"
                            {...storeOccupant.form(property.slug)}
                            onSuccess={() => closeQuickAdd()}
                            className="space-y-6"
                        >
                            {({ errors }) => (
                                <QuickAddFields
                                    units={units}
                                    currency={currency}
                                    errors={errors}
                                />
                            )}
                        </Form>
                    )}
                </ResponsiveOverlay>
            )}

            {canCreate && (
                <CsvImportSheet
                    open={isImportOpen}
                    onClose={() => setOverlayParam('import', null)}
                    route={() => importOccupants({ property: property.slug })}
                    idPrefix="occupants"
                    title="Import tenants"
                    description="Upload a CSV to move tenants in, one row each. Rows with problems are reported and skipped."
                    required={['First name', 'Unit', 'Rent amount']}
                    optional={[
                        'Last name',
                        'Email (enables portal access)',
                        'Phone',
                        'National ID',
                    ]}
                    canImport={canCreate}
                    buttonLabel="Import"
                />
            )}

            {canCreate && (
                <ResponsiveOverlay
                    open={isCreateOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title="Add an occupant"
                    description={`Assign someone to a unit in ${property.name}.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeSheets}
                            actions={[
                                {
                                    label: 'Add occupant',
                                    form: 'occupant-create-form',
                                    type: 'submit',
                                    disabled: !createValid,
                                },
                            ]}
                        />
                    }
                >
                    <button
                        type="button"
                        onClick={switchToQuickAdd}
                        className="mb-4 text-sm text-accent-text underline underline-offset-4 hover:text-accent-text-hover"
                    >
                        Just the basics? Name, phone, unit, rent.
                    </button>
                    {units === undefined ||
                    templates === undefined ||
                    availableTokens === undefined ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : (
                        <Form
                            id="occupant-create-form"
                            {...storeOccupant.form(property.slug)}
                            onSuccess={() => closeSheets()}
                            className="space-y-6"
                        >
                            {({ errors }) => (
                                <OccupantFormFields
                                    key={`create-${preselectedUnit ?? 'none'}`}
                                    units={units}
                                    templates={templates}
                                    availableTokens={availableTokens}
                                    errors={errors}
                                    idPrefix="create"
                                    propertyName={property.name}
                                    onValidityChange={setCreateValid}
                                    showMoveIn
                                    defaults={
                                        preselectedUnit !== null
                                            ? { unit_ids: [preselectedUnit] }
                                            : undefined
                                    }
                                />
                            )}
                        </Form>
                    )}
                </ResponsiveOverlay>
            )}

            {canEdit && focusedOccupant && (
                <ResponsiveOverlay
                    open={isEditOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title={`Edit ${focusedOccupant.first_name} ${focusedOccupant.last_name ?? ''}`.trim()}
                    description={`Update ${property.name} occupant details and unit assignments.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeSheets}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'occupant-edit-form',
                                    type: 'submit',
                                    disabled: !editValid,
                                },
                            ]}
                        />
                    }
                >
                    {units === undefined ||
                    templates === undefined ||
                    availableTokens === undefined ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : (
                        <Form
                            key={focusedOccupant.id}
                            id="occupant-edit-form"
                            {...updateOccupant.form({
                                property: property.slug,
                                occupant: focusedOccupant.id,
                            })}
                            onSuccess={() => closeSheets()}
                            className="space-y-6"
                        >
                            {({ errors }) => (
                                <OccupantFormFields
                                    key={focusedOccupant.id}
                                    units={units}
                                    templates={templates}
                                    availableTokens={availableTokens}
                                    errors={errors}
                                    idPrefix="edit"
                                    propertyName={property.name}
                                    onValidityChange={setEditValid}
                                    defaults={{
                                        first_name: focusedOccupant.first_name,
                                        last_name: focusedOccupant.last_name,
                                        email: focusedOccupant.email,
                                        phone: focusedOccupant.phone,
                                        national_id:
                                            focusedOccupant.national_id,
                                        gender: focusedOccupant.gender ?? null,
                                        status: focusedOccupant.status,
                                        unit_ids:
                                            focusedOccupant.all_unit_ids ??
                                            focusedOccupant.units.map(
                                                (unit) => unit.id,
                                            ),
                                        lease: focusedOccupant.lease
                                            ? {
                                                  starts_at:
                                                      focusedOccupant.lease
                                                          .starts_at,
                                                  rent_amount:
                                                      focusedOccupant.lease
                                                          .rent_amount !== null
                                                          ? Number(
                                                                focusedOccupant
                                                                    .lease
                                                                    .rent_amount,
                                                            )
                                                          : null,
                                                  rent_frequency:
                                                      focusedOccupant.lease
                                                          .rent_frequency,
                                                  rent_due_day:
                                                      focusedOccupant.lease
                                                          .rent_due_day,
                                                  deposit:
                                                      focusedOccupant.lease
                                                          .deposit !== null
                                                          ? Number(
                                                                focusedOccupant
                                                                    .lease
                                                                    .deposit,
                                                            )
                                                          : null,
                                                  currency:
                                                      focusedOccupant.lease
                                                          .currency,
                                                  agreement_text:
                                                      focusedOccupant.lease
                                                          .agreement_text,
                                                  agreement_document_name:
                                                      focusedOccupant.lease
                                                          .agreement_document_name,
                                                  agreement_document_url:
                                                      focusedOccupant.lease
                                                          .agreement_document_url,
                                              }
                                            : null,
                                    }}
                                />
                            )}
                        </Form>
                    )}
                </ResponsiveOverlay>
            )}

            {canEdit &&
                moveInTarget &&
                (moveInTarget.pending_leases?.length ?? 0) > 0 && (
                    <ResponsiveOverlay
                        open={isMoveInOpen}
                        onOpenChange={(open) => {
                            if (!open) {
                                closeMoveIn();
                            }
                        }}
                        title={`Move in ${moveInTarget.first_name} ${moveInTarget.last_name ?? ''}`.trim()}
                        description="Record the deposit and first-period rent to start the booked lease."
                        footer={
                            <OverlayFooter
                                onCancel={closeMoveIn}
                                actions={[
                                    {
                                        label: 'Confirm move-in',
                                        form: 'occupant-move-in-form',
                                        type: 'submit',
                                    },
                                ]}
                            />
                        }
                    >
                        <Form
                            key={moveInTarget.id}
                            id="occupant-move-in-form"
                            {...moveInOccupant.form({
                                property: property.slug,
                                occupant: moveInTarget.id,
                            })}
                            options={{ preserveScroll: true }}
                            onSuccess={() => closeSheets()}
                            className="grid gap-4 py-2"
                        >
                            {({ errors }) => (
                                <>
                                    <dl className="grid gap-x-6 gap-y-3 text-sm">
                                        {(
                                            moveInTarget.pending_leases ?? []
                                        ).map((lease) => (
                                            <div
                                                key={lease.lease_id}
                                                className="grid grid-cols-[8rem_1fr] gap-2"
                                            >
                                                <dt className="text-muted-foreground">
                                                    {lease.unit_name ?? 'Unit'}
                                                </dt>
                                                <dd className="min-w-0 break-words">
                                                    {formatMoney(
                                                        lease.rent_amount,
                                                        lease.currency,
                                                    )}
                                                    {lease.deposit != null &&
                                                        ` + ${formatMoney(lease.deposit, lease.currency)} deposit`}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>

                                    <div className="grid gap-2">
                                        <Label htmlFor="move-in-date">
                                            Move-in date
                                        </Label>
                                        <Input
                                            id="move-in-date"
                                            name="move_in_date"
                                            type="date"
                                            defaultValue={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                        />
                                        <InputError
                                            message={errors.move_in_date}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="move-in-method">
                                            Payment method
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="payment_method"
                                            value={moveInMethod}
                                        />
                                        <Select
                                            value={moveInMethod}
                                            onValueChange={setMoveInMethod}
                                        >
                                            <SelectTrigger
                                                id="move-in-method"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[
                                                    'cash',
                                                    'mpesa',
                                                    'bank',
                                                    'cheque',
                                                    'card',
                                                    'other',
                                                ].map((value) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {value === 'mpesa'
                                                            ? 'M-Pesa'
                                                            : value
                                                                  .charAt(0)
                                                                  .toUpperCase() +
                                                              value.slice(1)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.payment_method}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="move-in-reference">
                                            Reference (optional)
                                        </Label>
                                        <Input
                                            id="move-in-reference"
                                            name="payment_reference"
                                            placeholder="Receipt no."
                                        />
                                        <InputError
                                            message={errors.payment_reference}
                                        />
                                    </div>
                                </>
                            )}
                        </Form>
                    </ResponsiveOverlay>
                )}

            {canEdit &&
                moveOutTarget &&
                moveOutTarget.status !== 'moved_out' && (
                    <Dialog
                        open={isMoveOutOpen}
                        onOpenChange={(open) => !open && closeSheets()}
                    >
                        <DialogContent>
                            <DialogTitle>
                                Move {moveOutTarget.first_name}{' '}
                                {moveOutTarget.last_name ?? ''} out?
                            </DialogTitle>
                            <DialogDescription>
                                This ends their active lease(s) — kept for their
                                rental history — and frees their units. Each
                                ended lease holding a deposit gets its refund
                                auto-filed for inspection, and the renter
                                reverts to a searcher. Confirm with your
                                password.
                            </DialogDescription>
                            <Form
                                {...moveOutOccupant.form({
                                    property: property.slug,
                                    occupant: moveOutTarget.id,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="moveout-password"
                                                className="sr-only"
                                            >
                                                Password
                                            </Label>
                                            <PasswordInput
                                                id="moveout-password"
                                                name="password"
                                                placeholder="Password"
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <OverlayFooter
                                                cancelClosesDialog
                                                actions={[
                                                    {
                                                        label: 'Move out',
                                                        tone: 'danger',
                                                        type: 'submit',
                                                    },
                                                ]}
                                            />
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}
        </>
    );
}

OccupantsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: propertiesIndex().url,
        },
        {
            title: 'Occupants',
        },
    ],
};

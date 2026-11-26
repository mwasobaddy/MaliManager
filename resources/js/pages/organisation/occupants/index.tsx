import { Head, router, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    FileText,
    KeyRound,
    ListFilter,
    LogOut,
    Search,
    SquarePen,
    Trash2,
    Upload,
    Users,
    Plus,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { CsvImportSheet } from '@/components/csv-actions';
import { DataTable } from '@/components/data-table';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OccupantFormFields from '@/components/occupant-form-fields';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import type { TemplateToken } from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Pagination } from '@/components/ui/pagination';
import { Spinner } from '@/components/ui/spinner';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
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

type PendingLease = {
    lease_id: number;
    unit_id: number | null;
    unit_name: string | null;
    rent_amount: string | null;
    currency: string | null;
    deposit: string | null;
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
    // Present on the record resolved for the view sheet. List rows omit them
    // because the card does not show them, and the sheet is the only reader.
    gender?: string | null;
    added_at?: string | null;
    lease?: Lease | null;
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

                    return (
                        <div
                            className="flex justify-end gap-1"
                            onClick={(event) => event.stopPropagation()}
                        >
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
                        </div>
                    );
                },
            },
        ],
        [canEdit, canDelete],
    );

    const money = (
        currency: string | null | undefined,
        amount: string | null,
    ) => (amount === null ? '—' : `${currency ?? ''} ${amount}`.trim());

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
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={openQuickAdd}
                                className="ml-auto"
                            >
                                <Zap className="size-4" />
                                Quick add
                            </Button>
                            <ActionIconButton
                                icon={<Upload />}
                                label="Import occupants"
                                color="teal"
                                onClick={() => setOverlayParam('import', '1')}
                            />
                            <LabelledIconButton
                                icon={<Plus />}
                                label="Add occupant"
                                tooltip="Add an occupant to this property"
                                color="primary"
                                onClick={openCreate}
                                className="max-w-20 sm:max-w-24"
                            />
                        </>
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
                                                onClick={openCreate}
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
                            {viewedOccupant.lease && (
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
                                            {unit.type ?? '—'}
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
                        <h3 className="text-sm font-medium">Active lease</h3>
                        {viewedOccupant.lease ? (
                            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'Starts',
                                            viewedOccupant.lease.starts_at ??
                                                '—',
                                        ],
                                        [
                                            'Rent',
                                            `${money(
                                                viewedOccupant.lease.currency,
                                                viewedOccupant.lease
                                                    .rent_amount,
                                            )}${
                                                viewedOccupant.lease
                                                    .rent_frequency
                                                    ? ` / ${viewedOccupant.lease.rent_frequency}`
                                                    : ''
                                            }`,
                                        ],
                                        [
                                            'Deposit',
                                            money(
                                                viewedOccupant.lease.currency,
                                                viewedOccupant.lease.deposit,
                                            ),
                                        ],
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
                                        </dd>
                                    </div>
                                ))}

                                <div className="grid grid-cols-[8rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        Agreement
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {viewedOccupant.lease
                                            .agreement_document_url ? (
                                            <a
                                                href={
                                                    viewedOccupant.lease
                                                        .agreement_document_url
                                                }
                                                className="underline underline-offset-4"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {viewedOccupant.lease
                                                    .agreement_document_name ??
                                                    'Download'}
                                            </a>
                                        ) : (
                                            'None uploaded'
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No active lease in this property.
                            </p>
                        )}
                    </div>
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
                                        unit_ids: focusedOccupant.units.map(
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
                                rental history — and frees their units. The
                                renter reverts to a searcher.
                            </DialogDescription>
                            <Form
                                {...moveOutOccupant.form({
                                    property: property.slug,
                                    occupant: moveOutTarget.id,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
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

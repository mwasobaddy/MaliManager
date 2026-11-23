import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Download,
    FileText,
    ListFilter,
    MoreHorizontal,
    Power,
    Printer,
    Search,
    Send,
    Sparkles,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { OverlayFooter } from '@/components/overlay-footer';
import PasswordInput from '@/components/password-input';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { TableOverflowMenu } from '@/components/table-overflow-menu';
import { DataTable } from '@/components/data-table';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
import { page as draftingPage } from '@/routes/tenant/drafting';
import {
    end as endLease,
    index as leasesIndex,
    exportMethod as exportLeases,
    remind as remindLease,
    remindBulk as remindBulkLeases,
    renewalSuggestion,
} from '@/routes/tenant/leases';
import { agreement as agreementPrintRoute } from '@/routes/tenant/occupants';
import { index as propertiesIndex } from '@/routes/tenant/properties';

type LeaseRow = {
    id: number;
    occupant_id: number;
    occupant_name: string;
    unit_name: string | null;
    rent_amount: string | null;
    currency: string | null;
    starts_at: string | null;
    ends_at: string | null;
    status: string;
    is_active: boolean;
    property_name?: string | null;
    expiring_soon?: boolean;
    outstanding: string;
    is_overdue: boolean;
    days_overdue: number;
    last_reminded_ago: string | null;
    has_agreement_text: boolean;
    has_agreement_document: boolean;
};

type Props = {
    leases: {
        data: LeaseRow[];
        current_page: number;
        last_page: number;
        total: number;
        links?: unknown;
    };
    focusedLease: LeaseRow | null;
    filters: {
        search: string;
        status: string;
        expiring: boolean;
        overdue: boolean;
    };
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function LeasesIndex({ leases, focusedLease }: Props) {
    const { context } = usePage().props;
    const propertySlug = context?.property?.slug ?? '';
    const permissions = context?.permissions ?? [];
    const canEndLease = permissions.includes('lease.delete');
    const canRemind = permissions.includes('lease.manage');
    const aiEnabled = usePage().props.auth?.ai_enabled ?? false;

    const [selected, setSelected] = useState<number[]>([]);
    const [passwordError, setPasswordError] = useState('');
    const { query, setOverlayParam } = useOverlayParam();
    const [isTableLoading, setIsTableLoading] = useState(false);
    const [isMoreOpen, setIsMoreOpen] = useState(false);

    const filters = {
        search: query.get('search') ?? '',
        status: query.get('status') ?? '',
        expiring: query.get('expiring') === '1',
        overdue: query.get('overdue') === '1',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';
    const isExportOpen =
        query.get('export') !== null && query.get('export') !== '';

    // Both dialogs are addressed by the URL so they survive reloads and Back
    // closes them — never useState. A view-sheet deep link resolves
    // server-side, so rows off the current page fall back to focusedLease
    // rather than a stale row.
    const dialogLeaseId = Number(query.get('lease'));
    const dialogLease =
        Number.isFinite(dialogLeaseId) && dialogLeaseId > 0
            ? focusedLease?.id === dialogLeaseId
                ? focusedLease
                : (leases.data.find((lease) => lease.id === dialogLeaseId) ??
                  null)
            : null;
    const endTarget = query.get('dialog') === 'end-lease' ? dialogLease : null;
    const suggestTarget =
        query.get('dialog') === 'suggest' ? dialogLease : null;

    // The record is a sheet on this page, so the id travels in the URL and
    // the server resolves it into `focusedLease`. Falling back to the
    // visible rows keeps opening from the list a single request.
    const viewedLeaseId = Number(query.get('lease'));
    const isViewOpen = query.get('mode') === 'view' && viewedLeaseId > 0;
    const viewedLease = isViewOpen
        ? (focusedLease ??
          leases.data.find((lease) => lease.id === viewedLeaseId) ??
          null)
        : null;

    const [suggestion, setSuggestion] = useState<{
        suggested_rent: number;
        reasoning: string | null;
    } | null>(null);
    const [suggestBusy, setSuggestBusy] = useState(false);

    function openLeaseDialog(lease: LeaseRow, dialog: 'end-lease' | 'suggest') {
        setPasswordError('');
        setOverlayParam('dialog', dialog, {
            keep: { lease: String(lease.id) },
        });
    }

    function closeLeaseDialog() {
        setOverlayParam('dialog', null, {
            replace: true,
            keep: { lease: '' },
        });
    }

    function openView(lease: LeaseRow) {
        setOverlayParam('mode', 'view', {
            only: ['leases', 'focusedLease'],
            keep: { lease: String(lease.id) },
        });
    }

    function closeView() {
        setOverlayParam('mode', null, {
            replace: true,
            only: ['leases'],
            keep: { lease: '' },
        });
    }

    const passwordInput = useRef<HTMLInputElement>(null);

    const only = ['leases'];

    function tableParams(params: Record<string, string | number | boolean>) {
        return {
            search: filters.search,
            status: filters.status,
            expiring: filters.expiring ? '1' : '',
            overdue: filters.overdue ? '1' : '',
            sort: sort.column ?? '',
            direction: sort.direction,
            ...params,
        };
    }

    function refetchTable(
        params: Record<string, string | number | boolean>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(
            leasesIndex.url({ property: propertySlug }, { query: params }),
            {},
            {
                preserveState: true,
                replace: options?.replace ?? false,
                only,
                onFinish: () => setIsTableLoading(false),
            },
        );
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            tableParams({
                sort: first?.id ?? '',
                direction: first
                    ? first.desc
                        ? 'desc'
                        : 'asc'
                    : sort.direction,
            }),
            { replace: true },
        );
    }

    function changePage(nextPage: number) {
        refetchTable(tableParams({ page: nextPage }));
    }

    function applyFilters(params: Record<string, string | number | boolean>) {
        refetchTable(tableParams({ ...params, filters: '1' }), {
            replace: true,
        });
    }

    function clearFilters() {
        setSearchDraft('');
        refetchTable(
            tableParams({
                search: '',
                status: '',
                expiring: '',
                overdue: '',
                filters: '',
            }),
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const [serverSearch, setServerSearch] = useState(filters.search);
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
            refetchTable(tableParams({ search: searchDraft }), {
                replace: true,
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    // Selection never outlives the page: a refetch that drops a row also
    // drops its tick, so reminders can only name visible overdue leases.
    useEffect(() => {
        setSelected((current) =>
            current.filter((id) =>
                leases.data.some((lease) => lease.id === id),
            ),
        );
    }, [leases.data]);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    const fetchSuggestion = () => {
        if (!suggestTarget) {
            return;
        }

        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        setSuggestBusy(true);

        fetch(
            renewalSuggestion({
                property: propertySlug,
                lease: suggestTarget.id,
            }).url,
            { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } },
        )
            .then(async (response) => response.json())
            .then((data) => setSuggestion(data.error ? null : data))
            .finally(() => setSuggestBusy(false));
    };

    const submitEndLease = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!endTarget) {
            return;
        }

        const password = String(
            new FormData(event.currentTarget).get('password') ?? '',
        );

        router.post(
            endLease({
                property: propertySlug,
                lease: endTarget.id,
            }).url,
            { password },
            {
                preserveScroll: true,
                onSuccess: () => closeLeaseDialog(),
                onError: (errors) => {
                    setPasswordError(errors.password ?? '');
                    passwordInput.current?.focus();
                },
            },
        );
    };

    const sendReminder = (lease: LeaseRow) => {
        router.post(
            remindLease({
                property: propertySlug,
                lease: lease.id,
            }).url,
            {},
            { preserveScroll: true },
        );
    };

    const sendReminderFromView = (lease: LeaseRow) => {
        // Close on success, not alongside: the POST redirects back to this
        // same URL, which would otherwise win the race and reopen the sheet.
        router.post(
            remindLease({
                property: propertySlug,
                lease: lease.id,
            }).url,
            {},
            { preserveScroll: true, onSuccess: () => closeView() },
        );
    };

    const endLeaseFromView = (lease: LeaseRow) => {
        setPasswordError('');
        setOverlayParam('mode', null, {
            replace: true,
            only: ['leases'],
            keep: { lease: String(lease.id), dialog: 'end-lease' },
        });
    };

    const suggestFromView = (lease: LeaseRow) => {
        setSuggestion(null);
        setOverlayParam('mode', null, {
            replace: true,
            only: ['leases'],
            keep: { lease: String(lease.id), dialog: 'suggest' },
        });
    };

    const toggleSelected = (id: number) => {
        setSelected((prev) =>
            prev.includes(id)
                ? prev.filter((selectedId) => selectedId !== id)
                : [...prev, id],
        );
    };

    const sendBulkReminders = () => {
        router.post(
            remindBulkLeases({
                property: propertySlug,
            }).url,
            { lease_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    };

    /** Spoken description of the active filters, for the export sheet. */
    const activeFilterSummary =
        [
            filters.search ? `"${filters.search}"` : '',
            filters.status ? `status ${filters.status}` : '',
            filters.overdue ? 'overdue only' : '',
            filters.expiring ? 'expiring soon only' : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    /**
     * The filters the export has to carry. Reusing them is the point:
     * "Export all" must mean "everything the table is showing", not
     * "everything".
     */
    function exportParams(scope: 'all' | 'page', columns: string[]) {
        return {
            search: filters.search,
            status: filters.status,
            expiring: filters.expiring ? '1' : '',
            overdue: filters.overdue ? '1' : '',
            sort: sort.column ?? '',
            direction: sort.direction,
            scope,
            columns,
            ...(scope === 'page' ? { page: leases.current_page } : {}),
        };
    }

    function runExport(scope: 'all' | 'page', columns: string[]) {
        downloadCsv(
            exportLeases({ property: propertySlug }).url,
            exportParams(scope, columns),
        );
        setOverlayParam('export', null, { replace: true });
    }

    const statusBadges = (lease: LeaseRow) => (
        <>
            <Badge
                variant={lease.status === 'active' ? 'success' : 'neutral'}
                className="capitalize"
            >
                {lease.status}
            </Badge>
            {lease.expiring_soon && (
                <Badge className="ml-1 bg-status-warning-bg text-status-warning-fg">
                    Expiring soon
                </Badge>
            )}
            {lease.is_overdue && (
                <Badge variant="danger" className="ml-1">
                    Overdue
                    {lease.days_overdue > 0 ? ` ${lease.days_overdue}d` : ''}
                </Badge>
            )}
        </>
    );

    // Plain const, not memoized: the cells call overlay openers that close
    // over the current URL, and a stale memo would navigate with yesterday's
    // filters. Eighteen rows re-render cheaply; correctness first.
    const columns: ColumnDef<LeaseRow, unknown>[] = [
        {
            id: 'select',
            header: () => <span className="sr-only">Select</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div onClick={(event) => event.stopPropagation()}>
                    <Checkbox
                        checked={selected.includes(row.original.id)}
                        disabled={!row.original.is_overdue}
                        onCheckedChange={() => toggleSelected(row.original.id)}
                        aria-label={`Select ${row.original.occupant_name || 'lease'} for reminders`}
                    />
                </div>
            ),
        },
        {
            id: 'unit',
            accessorKey: 'unit_name',
            header: 'Unit',
            cell: ({ row }) => (
                <span className="font-medium">
                    {row.original.unit_name ?? '—'}
                </span>
            ),
        },
        {
            id: 'occupant',
            accessorKey: 'occupant_name',
            header: 'Occupant',
            cell: ({ row }) => <span>{row.original.occupant_name}</span>,
        },
        {
            id: 'rent',
            accessorKey: 'rent_amount',
            header: 'Rent',
            cell: ({ row }) => (
                <span>
                    {formatMoney(
                        row.original.rent_amount,
                        row.original.currency,
                    )}
                </span>
            ),
        },
        {
            id: 'balance',
            header: 'Balance',
            enableSorting: false,
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {row.original.outstanding !== '0.00'
                        ? formatMoney(
                              row.original.outstanding,
                              row.original.currency,
                          )
                        : '—'}
                </span>
            ),
        },
        {
            id: 'start',
            accessorKey: 'starts_at',
            header: 'Start',
            cell: ({ row }) => <span>{row.original.starts_at ?? '—'}</span>,
        },
        {
            id: 'end',
            accessorKey: 'ends_at',
            header: 'End',
            cell: ({ row }) => <span>{row.original.ends_at ?? '—'}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => statusBadges(row.original),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => {
                const lease = row.original;

                return (
                    <div
                        className="flex justify-end gap-1"
                        onClick={(event) => event.stopPropagation()}
                    >
                        {canRemind && lease.is_overdue && (
                            <div className="flex flex-col items-end gap-0.5">
                                <ActionIconButton
                                    icon={<Send />}
                                    label={`Remind ${lease.occupant_name ?? 'occupant'} about overdue rent`}
                                    color="teal"
                                    onClick={() => sendReminder(lease)}
                                />
                                {lease.last_reminded_ago && (
                                    <span className="pr-2 text-xs text-muted-foreground">
                                        Reminded {lease.last_reminded_ago}
                                    </span>
                                )}
                            </div>
                        )}
                        {lease.expiring_soon && (
                            <ActionIconButton
                                asChild
                                label="Draft renewal letter"
                                color="default"
                            >
                                <a
                                    href={`${draftingPage().url}?${new URLSearchParams(
                                        {
                                            type: 'lease_expiry_notice',
                                            tenant_name:
                                                lease.occupant_name ?? '',
                                            expiry_date: lease.ends_at ?? '',
                                        },
                                    )}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-test={`draft-renewal-${lease.id}`}
                                >
                                    <FileText />
                                </a>
                            </ActionIconButton>
                        )}
                        {(lease.has_agreement_text ||
                            lease.has_agreement_document) && (
                            <ActionIconButton
                                asChild
                                label="View agreement"
                                color="view"
                            >
                                <a
                                    href={
                                        agreementPrintRoute({
                                            property: propertySlug,
                                            occupant: lease.occupant_id,
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-test={`agreement-${lease.id}`}
                                >
                                    <Printer />
                                </a>
                            </ActionIconButton>
                        )}
                        {lease.expiring_soon && aiEnabled && (
                            <ActionIconButton
                                icon={<Sparkles />}
                                label="Suggest renewal rent"
                                color="brand"
                                onClick={() => {
                                    openLeaseDialog(lease, 'suggest');
                                    setSuggestion(null);
                                }}
                            />
                        )}
                        {canEndLease && lease.is_active && (
                            <ActionIconButton
                                icon={<Power />}
                                label={`End lease for ${lease.occupant_name ?? 'occupant'}`}
                                color="danger"
                                onClick={() =>
                                    openLeaseDialog(lease, 'end-lease')
                                }
                            />
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Leases" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        title="Leases"
                        description="Every lease in this property, with printable agreements."
                    />
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
                                placeholder="Search occupant or unit..."
                                aria-label="Search leases"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <div className="ml-auto hidden items-center gap-2 sm:flex">
                            <ActionIconButton
                                icon={<ListFilter />}
                                label="Filters"
                                color="teal"
                                className="coarse:min-h-9 coarse:min-w-9"
                                onClick={() =>
                                    isFiltersOpen
                                        ? setOverlayParam('filters', null, {
                                              replace: true,
                                          })
                                        : setOverlayParam('filters', '1')
                                }
                            />
                            <ActionIconButton
                                icon={<Download />}
                                label="Export"
                                color="teal"
                                className="coarse:min-h-9 coarse:min-w-9"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        </div>
                        <ActionIconButton
                            icon={<MoreHorizontal />}
                            label="More actions"
                            color="teal"
                            className="ml-auto sm:hidden coarse:min-h-9 coarse:min-w-9"
                            onClick={() => setIsMoreOpen(true)}
                        />
                    </div>

                    {canRemind && selected.length > 0 && (
                        <div className="flex flex-wrap items-center gap-3 border-b border-border-strong px-4 py-3">
                            <p className="text-sm text-muted-foreground">
                                {selected.length}{' '}
                                {selected.length === 1 ? 'lease' : 'leases'}{' '}
                                selected
                            </p>
                            <Button size="sm" onClick={sendBulkReminders}>
                                Send reminders
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected([])}
                            >
                                Clear
                            </Button>
                        </div>
                    )}

                    <DataTable
                        columns={columns}
                        data={leases.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(lease) => openView(lease)}
                        empty="No leases match your filters."
                    />
                </Card>

                <Pagination
                    currentPage={leases.current_page}
                    lastPage={leases.last_page}
                    total={leases.total}
                    label="leases"
                    onChange={changePage}
                />
            </div>

            <TableOverflowMenu
                open={isMoreOpen}
                onOpenChange={setIsMoreOpen}
                actions={[
                    {
                        icon: <ListFilter />,
                        label: 'Filters',
                        onSelect: () => setOverlayParam('filters', '1'),
                    },
                    {
                        icon: <Download />,
                        label: 'Export',
                        onSelect: () => setOverlayParam('export', '1'),
                    },
                ]}
            />

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of leases."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                onClick: () => {
                                    clearFilters();
                                    setOverlayParam('filters', null, {
                                        replace: true,
                                    });
                                },
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Status</legend>
                        <RadioGroup
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                            className="space-y-3"
                        >
                            {[
                                { value: 'all', label: 'Any status' },
                                { value: 'active', label: 'Active' },
                                { value: 'ended', label: 'Ended' },
                            ].map((option) => (
                                <div
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`lease-status-${option.value}`}
                                        value={option.value}
                                    />
                                    <Label
                                        htmlFor={`lease-status-${option.value}`}
                                        className="cursor-pointer"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="lease-filter-overdue"
                            checked={filters.overdue}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    overdue: checked ? '1' : '',
                                    ...(checked ? { status: 'active' } : {}),
                                })
                            }
                        />
                        <Label
                            htmlFor="lease-filter-overdue"
                            className="cursor-pointer text-sm"
                        >
                            Overdue only
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="lease-filter-expiring"
                            checked={filters.expiring}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    expiring: checked ? '1' : '',
                                    ...(checked ? { status: 'active' } : {}),
                                })
                            }
                        />
                        <Label
                            htmlFor="lease-filter-expiring"
                            className="cursor-pointer text-sm"
                        >
                            Expiring soon only
                        </Label>
                    </div>
                </div>
            </ResponsiveOverlay>

            <CsvExportSheet
                open={isExportOpen}
                onClose={() =>
                    setOverlayParam('export', null, { replace: true })
                }
                canExport={canRemind}
                total={leases.total}
                pageCount={leases.data.length}
                currentPage={leases.current_page}
                activeFilters={activeFilterSummary}
                columns={[
                    'ID',
                    'Unit',
                    'Occupant',
                    'Rent amount',
                    'Currency',
                    'Outstanding',
                    'Start',
                    'End',
                    'Status',
                ]}
                onExport={runExport}
            />

            {viewedLease && (
                <ResponsiveOverlay
                    open={isViewOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeView();
                        }
                    }}
                    title={`Lease for ${viewedLease.occupant_name || 'occupant'}`}
                    description={`${viewedLease.unit_name ?? 'No unit'} · ${viewedLease.starts_at ?? '—'} → ${viewedLease.ends_at ?? '—'}`}
                    actions={
                        <>
                            {(viewedLease.has_agreement_text ||
                                viewedLease.has_agreement_document) && (
                                <Button type="button" size="sm" asChild>
                                    <a
                                        href={
                                            agreementPrintRoute({
                                                property: propertySlug,
                                                occupant:
                                                    viewedLease.occupant_id,
                                            }).url
                                        }
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Printer className="size-4" />
                                        Agreement
                                    </a>
                                </Button>
                            )}
                            {canRemind && viewedLease.is_overdue && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="bg-brand-accent text-on-accent hover:bg-brand-accent/90"
                                    onClick={() =>
                                        sendReminderFromView(viewedLease)
                                    }
                                >
                                    <Send className="size-4" />
                                    Remind
                                </Button>
                            )}
                            {viewedLease.expiring_soon && aiEnabled && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="bg-action-view-fill text-white hover:bg-action-view-fill hover:brightness-95"
                                    onClick={() => suggestFromView(viewedLease)}
                                >
                                    <Sparkles className="size-4" />
                                    Suggest rent
                                </Button>
                            )}
                            {canEndLease && viewedLease.is_active && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="bg-action-danger-fill text-white hover:bg-action-danger-fill hover:brightness-95"
                                    onClick={() =>
                                        endLeaseFromView(viewedLease)
                                    }
                                >
                                    <Power className="size-4" />
                                    End lease
                                </Button>
                            )}
                        </>
                    }
                    footer={<OverlayFooter onCancel={closeView} />}
                >
                    <div className="flex flex-wrap gap-1 py-2">
                        {statusBadges(viewedLease)}
                    </div>

                    <dl className="grid gap-x-6 gap-y-3 py-2 text-sm">
                        {(
                            [
                                ['Occupant', viewedLease.occupant_name || '—'],
                                ['Unit', viewedLease.unit_name ?? '—'],
                                [
                                    'Rent amount',
                                    `${formatMoney(viewedLease.rent_amount, viewedLease.currency)}`,
                                ],
                                [
                                    'Outstanding',
                                    viewedLease.outstanding !== '0.00'
                                        ? formatMoney(
                                              viewedLease.outstanding,
                                              viewedLease.currency,
                                          )
                                        : 'Paid up',
                                ],
                                ['Starts', viewedLease.starts_at ?? '—'],
                                ['Ends', viewedLease.ends_at ?? '—'],
                                [
                                    'Last reminded',
                                    viewedLease.last_reminded_ago ?? 'Never',
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
                                <dd className="min-w-0 break-words">{value}</dd>
                            </div>
                        ))}

                        <div className="grid grid-cols-[8rem_1fr] gap-2">
                            <dt className="text-muted-foreground">Agreement</dt>
                            <dd className="min-w-0 break-words">
                                {viewedLease.has_agreement_text ||
                                viewedLease.has_agreement_document ? (
                                    <span className="flex flex-col gap-1">
                                        <a
                                            href={
                                                agreementPrintRoute({
                                                    property: propertySlug,
                                                    occupant:
                                                        viewedLease.occupant_id,
                                                }).url
                                            }
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="underline underline-offset-4"
                                        >
                                            View printable agreement
                                        </a>
                                        {viewedLease.expiring_soon && (
                                            <a
                                                href={`${draftingPage().url}?${new URLSearchParams(
                                                    {
                                                        type: 'lease_expiry_notice',
                                                        tenant_name:
                                                            viewedLease.occupant_name ??
                                                            '',
                                                        expiry_date:
                                                            viewedLease.ends_at ??
                                                            '',
                                                    },
                                                )}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="underline underline-offset-4"
                                            >
                                                Draft renewal letter
                                            </a>
                                        )}
                                    </span>
                                ) : (
                                    'None uploaded'
                                )}
                            </dd>
                        </div>
                    </dl>
                </ResponsiveOverlay>
            )}

            <Dialog
                open={endTarget !== null}
                onOpenChange={(open) => !open && closeLeaseDialog()}
            >
                <DialogContent>
                    <DialogTitle>
                        End lease for {endTarget?.occupant_name}?
                    </DialogTitle>
                    <DialogDescription>
                        This ends the lease on unit {endTarget?.unit_name} today
                        and keeps rental history. Please enter your password to
                        confirm.
                    </DialogDescription>
                    <form onSubmit={submitEndLease} className="space-y-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="end_lease_password"
                                className="sr-only"
                            >
                                Password
                            </Label>
                            <PasswordInput
                                id="end_lease_password"
                                name="password"
                                ref={passwordInput}
                                placeholder="Password"
                                autoComplete="current-password"
                            />
                            <InputError message={passwordError} />
                        </div>
                        <DialogFooter>
                            <OverlayFooter
                                cancelClosesDialog
                                actions={[
                                    {
                                        label: 'End lease',
                                        tone: 'danger',
                                        type: 'submit',
                                    },
                                ]}
                            />
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={suggestTarget !== null}
                onOpenChange={(open) => !open && closeLeaseDialog()}
            >
                <DialogContent>
                    <DialogTitle>
                        Renewal suggestion — {suggestTarget?.occupant_name}
                    </DialogTitle>
                    <DialogDescription>
                        Unit {suggestTarget?.unit_name}, ends{' '}
                        {suggestTarget?.ends_at}. Current rent:{' '}
                        {formatMoney(
                            suggestTarget?.rent_amount ?? null,
                            suggestTarget?.currency,
                        )}
                        .
                    </DialogDescription>

                    {suggestBusy ? (
                        <p className="text-sm text-muted-foreground">
                            Thinking…
                        </p>
                    ) : suggestion ? (
                        <div className="space-y-3">
                            <p className="text-2xl font-semibold tabular-nums">
                                {formatMoney(
                                    suggestion.suggested_rent,
                                    suggestTarget?.currency,
                                )}
                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                    / month
                                </span>
                            </p>
                            {suggestion.reasoning && (
                                <p className="text-sm text-muted-foreground">
                                    {suggestion.reasoning}
                                </p>
                            )}
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={`${draftingPage().url}?${new URLSearchParams(
                                        {
                                            type: 'lease_expiry_notice',
                                            tenant_name:
                                                suggestTarget?.occupant_name ??
                                                '',
                                            expiry_date:
                                                suggestTarget?.ends_at ?? '',
                                            suggested_rent: String(
                                                suggestion.suggested_rent,
                                            ),
                                        },
                                    )}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Draft renewal letter with this rent
                                </a>
                            </Button>
                        </div>
                    ) : (
                        <Button onClick={fetchSuggestion}>
                            <Sparkles className="size-4" />
                            Generate suggestion
                        </Button>
                    )}

                    <DialogFooter>
                        <OverlayFooter cancelClosesDialog cancelLabel="Close" />
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

LeasesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: propertiesIndex().url,
        },
        {
            title: 'Leases',
        },
    ],
};

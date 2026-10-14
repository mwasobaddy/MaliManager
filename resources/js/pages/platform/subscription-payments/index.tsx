import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Building2,
    Download,
    ListFilter,
    Pencil,
    Plus,
    Receipt,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { CsvImportSheet, downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as subscriptionPaymentRoutes from '@/routes/platform/subscription-payments';

type Option = { value: number; label: string };

type PaymentRow = {
    id: number;
    organization_id: number | null;
    organization: { id: number; name: string } | null;
    plan_id: number | null;
    plan: { id: number; name: string } | null;
    amount: number;
    currency: string;
    received_on: string;
    period_start: string | null;
    period_end: string | null;
    method: string | null;
    reference: string | null;
    notes: string | null;
};

type Props = {
    payments: {
        data: PaymentRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search: string; from: string; to: string };
    sort: { column: string | null; direction: 'asc' | 'desc' };
    /**
     * The row the URL points at, when it is not on the current page. The
     * controller only fills this in for a `?payment=` id the paginated result
     * set does not already contain.
     */
    focusedPayment?: PaymentRow | null;
    /** Optional server-side: absent until a form sheet asks for them. */
    organizations?: Option[];
    plans?: Option[];
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function SubscriptionPaymentsIndex({
    payments,
    filters,
    sort,
    focusedPayment,
    organizations,
    plans,
}: Props) {
    const page = usePage();
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const canCreate = permissions.includes('subscription.create');
    const canEdit = permissions.includes('subscription.edit');
    const canDelete = permissions.includes('subscription.delete');
    const canImport = permissions.includes('subscription.import');
    const canExport = permissions.includes('subscription.export');

    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const [isTableLoading, setIsTableLoading] = useState(false);

    // Read query params off Inertia's page.url, NOT window.location. Inertia
    // pushes history *after* React renders, so window.location is stale during
    // render and nothing re-renders afterwards — the overlay would not open
    // until a full page load. page.url is part of the reactive page object.
    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);

    const viewedId = Number(query.get('payment'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    // Prefer the row already on this page; fall back to the one the server
    // fetched for the URL. That fallback is what makes a shared link to a row
    // on another page work, and is why this overlay does not need its own page.
    const viewedPayment = isViewOpen
        ? (payments.data.find((payment) => payment.id === viewedId) ??
          focusedPayment)
        : undefined;

    // One row sheet with switchable content rather than a sheet per action:
    // stacking Radix modals fights over the focus trap and double-dims the page.
    //
    // `confirm-delete` is a fourth mode rather than a separate second overlay:
    // the first Delete press only asks whether you mean it, and the password
    // prompt appears inside the same sheet once you have said yes.
    type RowMode = 'view' | 'edit' | 'delete' | 'confirm-delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isCreateOpen = query.get('create') === '1';
    const isImportOpen = query.get('import') === '1';
    const isExportOpen = query.get('export') === '1';

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(subscriptionPaymentRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['payments', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     * `only` keeps this off the optional organization and plan lists.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(subscriptionPaymentRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['payments', 'filters', 'sort'],
            onFinish: () => setIsTableLoading(false),
        });
    }

    // Overlays push history so Back closes them; closing replaces so Back does
    // not walk back through every sheet the user opened.
    function setOverlayParam(
        key: string,
        value: string | null,
        options?: {
            replace?: boolean;
            /** Extra params to carry through, e.g. the row being opened. */
            keep?: Record<string, string>;
        },
    ) {
        navigate(
            {
                search: filters.search,
                from: filters.from,
                to: filters.to,
                sort: sort.column ?? '',
                direction: sort.direction,
                ...(options?.keep ?? {}),
                [key]: value ?? '',
            },
            { replace: options?.replace },
        );
    }

    /** Close the row sheet, dropping both the row id and the mode. */
    function closeRowSheet() {
        navigate(
            {
                search: filters.search,
                from: filters.from,
                to: filters.to,
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    /**
     * Opens the row overlay in `mode`. Every mode is available on every
     * viewport — on a phone the overlay is a bottom drawer rather than a side
     * sheet, but nothing has to be reached by redirecting to a separate page.
     */
    function openRow(payment: PaymentRow, mode: RowMode) {
        setOverlayParam('mode', mode, {
            replace: mode === 'view',
            keep: { payment: String(payment.id) },
        });
    }

    function changePage(nextPage: number) {
        refetchTable({
            search: filters.search,
            from: filters.from,
            to: filters.to,
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];
        refetchTable(
            {
                search: filters.search,
                from: filters.from,
                to: filters.to,
                sort: first?.id ?? '',
                direction: first?.desc ? 'desc' : 'asc',
            },
            { replace: true },
        );
    }

    // `organizations` and `plans` are optional props: the server does not send
    // them unless asked. Fetch them the first time a form sheet opens, and only
    // then. Loading is derived from the prop being absent rather than tracked
    // separately, so there is no second source of truth to fall out of sync.
    const needsOptions = isCreateOpen || rowMode === 'edit';

    useEffect(() => {
        if (needsOptions && (organizations === undefined || plans === undefined)) {
            router.reload({ only: ['organizations', 'plans'] });
        }
    }, [needsOptions, organizations, plans]);

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        // Never fire on mount — the server already sent the filtered result.
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
                    from: filters.from,
                    to: filters.to,
                    sort: sort.column ?? '',
                    direction: sort.direction,
                },
                { replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    // Keep the box in step with Back/Forward and with the filters overlay
    // clearing the search. Adjusting during render (rather than in an effect)
    // is React's documented pattern for derived-from-props state, and avoids
    // the extra render pass an effect would cost.
    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    /** Spoken description of the active filters, for the export sheet. */
    const activeFilterSummary =
        [
            filters.search ? 'the search' : '',
            filters.from || filters.to ? 'the date range' : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    const columns = useMemo<ColumnDef<PaymentRow, unknown>[]>(() => {
        const cells: ColumnDef<PaymentRow, unknown>[] = [
            {
                id: 'organization',
                accessorKey: 'organization',
                header: 'Organization',
                // Sorting by the related row's name needs a join or a subquery,
                // so there is no meaningful server-side sort to expose here.
                enableSorting: false,
                cell: ({ row }) => {
                    const organization = row.original.organization;

                    if (!organization) {
                        return <span className="text-xs">—</span>;
                    }

                    return (
                        <div className="flex min-w-0 items-center gap-2">
                            <Building2 className="size-4 shrink-0 text-muted-foreground" />
                            <span
                                title={organization.name}
                                className="block max-w-48 truncate font-medium"
                            >
                                {organization.name}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'plan',
                accessorKey: 'plan',
                header: 'Plan',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.plan ? (
                        <span
                            title={row.original.plan.name}
                            className="block max-w-40 truncate text-xs"
                        >
                            {row.original.plan.name}
                        </span>
                    ) : (
                        <span className="text-xs">—</span>
                    ),
            },
            {
                id: 'amount',
                accessorKey: 'amount',
                header: 'Amount',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap text-right font-medium tabular-nums">
                        {row.original.currency}{' '}
                        {row.original.amount.toLocaleString()}
                    </span>
                ),
            },
            {
                id: 'received_on',
                accessorKey: 'received_on',
                header: 'Received',
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap tabular-nums">
                        {row.original.received_on}
                    </span>
                ),
            },
            {
                id: 'method',
                accessorKey: 'method',
                header: 'Method',
                cell: ({ row }) =>
                    row.original.method ? (
                        <span
                            title={row.original.method}
                            className="block max-w-32 truncate text-xs"
                        >
                            {row.original.method}
                        </span>
                    ) : (
                        <span className="text-xs">—</span>
                    ),
            },
            {
                // Free text from a payment gateway — unbounded length and often
                // noise, so it truncates and keeps the full value on hover.
                id: 'reference',
                accessorKey: 'reference',
                header: 'Reference',
                cell: ({ row }) =>
                    row.original.reference ? (
                        <span
                            title={row.original.reference}
                            className="block max-w-40 truncate font-mono text-xs sm:max-w-56"
                        >
                            {row.original.reference}
                        </span>
                    ) : (
                        <span className="text-xs">—</span>
                    ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const payment = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            // The whole row opens the detail sheet; interactive
                            // controls inside it must not also trigger that.
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canEdit && (
                                <ActionIconButton
                                    icon={<Pencil />}
                                    label={`Edit payment from ${
                                        payment.organization?.name ??
                                        'this organization'
                                    }`}
                                    color="edit"
                                    onClick={() => openRow(payment, 'edit')}
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete payment ${
                                        payment.reference ?? payment.id
                                    }`}
                                    color="danger"
                                    onClick={() => openRow(payment, 'delete')}
                                />
                            )}
                        </div>
                    );
                },
            },
        ];

        return cells;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canEdit, canDelete, filters.search, filters.from, filters.to, sort.column, sort.direction]);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    /** Current filters as export query params, shared by both scopes. */
    function exportParams(scope: 'all' | 'page') {
        return {
            search: filters.search,
            from: filters.from,
            to: filters.to,
            sort: sort.column ?? '',
            direction: sort.direction,
            scope,
            ...(scope === 'page'
                ? { page: payments.current_page }
                : {}),
        };
    }

    function runExport(scope: 'all' | 'page') {
        downloadCsv(subscriptionPaymentRoutes.exportMethod().url, exportParams(scope));
        setOverlayParam('export', null, { replace: true });
    }

    return (
        <>
            <Head title="Subscription payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Subscription payments"
                        description="Income received from organizations for their subscriptions."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New payment"
                            tooltip="Record a subscription payment"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). "New payment" is a page-level
                 * CTA and stays in the page header.
                 *
                 * `gap-0 py-0` is what makes the band flush with the card edge:
                 * the Card's own padding would otherwise inset the search row
                 * relative to the table below it.
                 */}
                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search reference, method, organization..."
                                aria-label="Search subscription payments"
                                // shadcn Input is bg-transparent, which leaves
                                // the field defined by a 1.2:1 border alone. On
                                // a card surface it needs a fill + a stronger
                                // border to be readable in both schemes.
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', null, {
                                          replace: true,
                                      })
                                    : setOverlayParam('filters', '1')
                            }
                        />
                        {canImport && (
                            <ActionIconButton
                                icon={<Upload />}
                                label="Import from CSV"
                                color="teal"
                                onClick={() =>
                                    setOverlayParam('import', '1')
                                }
                            />
                        )}
                        {canExport && (
                            <ActionIconButton
                                icon={<Download />}
                                label="Export"
                                color="teal"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        )}
                    </div>
                    <DataTable
                        columns={columns}
                        data={payments.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        loadingRows={15}
                        onRowClick={(payment) => openRow(payment, 'view')}
                        empty="No subscription payments match your filters."
                    />
                </Card>

                {payments.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {payments.current_page} of {payments.last_page} (
                            {payments.total} payments)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={payments.current_page <= 1}
                                onClick={() =>
                                    changePage(payments.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    payments.current_page >= payments.last_page
                                }
                                onClick={() =>
                                    changePage(payments.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* The row sheet: view, edit, delete and confirm share one overlay. */}
            <ResponsiveOverlay
                open={isViewOpen && viewedPayment !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={
                    viewedPayment?.organization?.name ?? 'Payment'
                }
                description={
                    !viewedPayment
                        ? 'This payment is not on the current page of results.'
                        : rowMode === 'delete'
                          ? 'This soft-deletes the record. The audit trail is kept.'
                          : rowMode === 'confirm-delete'
                            ? 'Confirm with your password.'
                            : rowMode === 'edit'
                              ? 'Update the amount, period or reference.'
                              : 'Details for this payment.'
                }
                footer={
                    rowMode === 'delete' && viewedPayment ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Continue to delete',
                                    tone: 'danger-ghost',
                                    icon: <Trash2 className="size-4" />,
                                    onClick: () =>
                                        setOverlayParam('mode', 'confirm-delete', {
                                            keep: {
                                                payment: String(
                                                    viewedPayment.id,
                                                ),
                                            },
                                        }),
                                },
                            ]}
                        />
                    ) : rowMode === 'confirm-delete' && viewedPayment ? (
                        <OverlayFooter
                            onCancel={() => openRow(viewedPayment, 'delete')}
                            actions={[
                                {
                                    label: 'Delete payment',
                                    form: 'payment-delete-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'edit' && viewedPayment ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'payment-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : (
                        <OverlayFooter
                            actions={[
                                {
                                    label: 'Edit',
                                    tone: 'secondary',
                                    icon: <Pencil className="size-4" />,
                                    onClick: () =>
                                        viewedPayment &&
                                        openRow(viewedPayment, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    )
                }
            >
                {viewedPayment &&
                    (rowMode === 'edit' ? (
                        <Form
                            key={viewedPayment.id}
                            id="payment-edit-form"
                            method="patch"
                            action={subscriptionPaymentRoutes.update(
                                viewedPayment.id,
                            )}
                            onSuccess={() => closeRowSheet()}
                        >
                            <PaymentFormFields
                                payment={viewedPayment}
                                organizations={organizations}
                                plans={plans}
                                errors={errors}
                            />
                        </Form>
                    ) : rowMode === 'delete' ? (
                        <p className="text-sm">
                            Deleting this payment removes{' '}
                            <span className="font-medium">
                                {viewedPayment.currency}{' '}
                                {viewedPayment.amount.toLocaleString()}
                            </span>{' '}
                            received on {viewedPayment.received_on} from{' '}
                            <span className="font-medium">
                                {viewedPayment.organization?.name ??
                                    'an unlinked organization'}
                            </span>
                            . It drops out of the revenue figures. The record is
                            kept, so it can be restored.
                        </p>
                    ) : rowMode === 'confirm-delete' ? (
                        <Form
                            id="payment-delete-form"
                            method="delete"
                            action={subscriptionPaymentRoutes.destroy(
                                viewedPayment.id,
                            )}
                            onSuccess={() => closeRowSheet()}
                            className="grid gap-3"
                        >
                            <p className="text-sm">
                                This soft-deletes the payment. Confirm with your
                                password to continue.
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="delete-password">
                                    Confirm with your password
                                </Label>
                                <Input
                                    id="delete-password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                        </Form>
                    ) : (
                        <PaymentDetail payment={viewedPayment} />
                    ))}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Record a payment"
                description="Income received from an organization for their subscription."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Record payment',
                                form: 'payment-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="payment-create-form"
                    method="post"
                    action={subscriptionPaymentRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                >
                    <PaymentFormFields
                        organizations={organizations}
                        plans={plans}
                        errors={errors}
                    />
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of payments."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                tone: 'secondary',
                                onClick: () =>
                                    refetchTable(
                                        {
                                            search: '',
                                            from: '',
                                            to: '',
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                        },
                                        { replace: true },
                                    ),
                            },
                        ]}
                    />
                }
            >
                {/*
                 * A date range rather than a radio group: `method` is free text
                 * from a payment gateway, so its option set is open and a
                 * closed list of choices would be a lie. A radio group only
                 * suits a small, fixed set — see the users filters.
                 */}
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label>Received</Label>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-from"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    From
                                </Label>
                                <Input
                                    id="filter-from"
                                    type="date"
                                    value={filters.from}
                                    onChange={(event) => {
                                        const from = event.target.value;

                                        refetchTable(
                                            {
                                                search: filters.search,
                                                from,
                                                to: filters.to,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        );
                                    }}
                                />
                                {errors.from && (
                                    <p className="text-xs text-destructive">
                                        {errors.from}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-to"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    To
                                </Label>
                                <Input
                                    id="filter-to"
                                    type="date"
                                    value={filters.to}
                                    onChange={(event) =>
                                        refetchTable(
                                            {
                                                search: filters.search,
                                                from: filters.from,
                                                to: event.target.value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                />
                                {errors.to && (
                                    <p className="text-xs text-destructive">
                                        {errors.to}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-search">Search</Label>
                        <Input
                            id="filter-search"
                            value={searchDraft}
                            onChange={(event) =>
                                setSearchDraft(event.target.value)
                            }
                            placeholder="Reference, method, organization..."
                        />
                        {errors.search && (
                            <p className="text-xs text-destructive">
                                {errors.search}
                            </p>
                        )}
                    </div>
                </div>
            </ResponsiveOverlay>

            <CsvExportSheet
                open={isExportOpen}
                onClose={() => setOverlayParam('export', null, { replace: true })}
                canExport={canExport}
                total={payments.total}
                pageCount={payments.data.length}
                currentPage={payments.current_page}
                activeFilters={activeFilterSummary}
                onExport={runExport}
            />

            <CsvImportSheet
                open={isImportOpen}
                onClose={() => setOverlayParam('import', null, { replace: true })}
                route={subscriptionPaymentRoutes.importMethod}
                idPrefix="subscription-payments"
                title="Import subscription payments"
                description="Upload a CSV to create payment records."
                required={["Amount", "Received On"]}
                optional={["Currency", "Organization", "Plan", "Method", "Reference", "Notes"]}
                canImport={canImport}
                buttonLabel="Import payments"
            />
        </>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

/** Read-only summary of a payment, for the row sheet's default mode. */
function PaymentDetail({ payment }: { payment: PaymentRow }) {
    return (
        <div className="grid gap-4 py-2">
            <div className="flex items-center gap-3">
                <span className="text-muted-foreground flex items-center gap-2 text-sm">
                    <Receipt className="size-4" />
                    {payment.received_on}
                </span>
                <span className="text-lg font-semibold tabular-nums">
                    {payment.currency} {payment.amount.toLocaleString()}
                </span>
            </div>

            <dl className="grid gap-3 text-sm">
                {[
                    [
                        'Organization',
                        payment.organization?.name ?? 'Unlinked',
                    ],
                    ['Plan', payment.plan?.name ?? '—'],
                    ['Method', payment.method ?? '—'],
                    ['Reference', payment.reference ?? '—'],
                    ['Period start', payment.period_start ?? '—'],
                    ['Period end', payment.period_end ?? '—'],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="grid grid-cols-[9rem_1fr] gap-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="min-w-0 break-words">{value}</dd>
                    </div>
                ))}
            </dl>

            {payment.notes && (
                <div className="grid gap-2">
                    <div className="text-sm font-medium">Notes</div>
                    <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                        {payment.notes}
                    </p>
                </div>
            )}
        </div>
    );
}

/**
 * The create and edit forms share one component.
 *
 * Every control here is a real form control that serialises itself, so unlike
 * the plans form there are no hidden inputs to pair up. The two Selects need
 * care of a different kind: Radix reserves the empty value for the placeholder,
 * so "no organization" and "no plan" are carried by a hidden `""` input that is
 * always present — that is what submits `null` rather than omitting the key.
 *
 * `organization_id` and `plan_id` are only sent when a selection is made.
 */
function PaymentFormFields({
    payment,
    organizations,
    plans,
    errors,
}: {
    payment?: PaymentRow;
    organizations?: Option[];
    plans?: Option[];
    errors: Record<string, string>;
}) {
    const isLoadingOptions = organizations === undefined || plans === undefined;

    const [organizationId, setOrganizationId] = useState(
        payment?.organization_id ? String(payment.organization_id) : undefined,
    );
    const [planId, setPlanId] = useState(
        payment?.plan_id ? String(payment.plan_id) : undefined,
    );

    const organizationOptions = organizations ?? [];
    const planOptions = plans ?? [];

    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className="grid gap-3 py-2">
            <div className="grid gap-2">
                <Label htmlFor="payment-amount">Amount</Label>
                <div className="grid grid-cols-[1fr_6rem] gap-3">
                    <Input
                        id="payment-amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min={0}
                        defaultValue={String(payment?.amount ?? '')}
                        required
                    />
                    <Input
                        name="currency"
                        maxLength={3}
                        defaultValue={payment?.currency ?? 'KES'}
                        aria-label="Currency"
                        required
                    />
                </div>
                <FieldError message={errors.amount} />
                <FieldError message={errors.currency} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payment-received_on">Received on</Label>
                <Input
                    id="payment-received_on"
                    name="received_on"
                    type="date"
                    defaultValue={payment?.received_on ?? today}
                    required
                />
                <FieldError message={errors.received_on} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="payment-organization_id">
                        Organization
                    </Label>
                    {isLoadingOptions ? (
                        <Input
                            disabled
                            aria-label="Loading organizations"
                            placeholder="Loading organizations…"
                        />
                    ) : (
                        <>
                            <Select
                                value={organizationId}
                                onValueChange={setOrganizationId}
                            >
                                <SelectTrigger id="payment-organization_id">
                                    <SelectValue placeholder="Not linked" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizationOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {/* Radix reserves "" for the placeholder, so this
                                hidden field is what submits `null`. */}
                            <input
                                type="hidden"
                                name="organization_id"
                                value={organizationId ?? ''}
                                readOnly
                            />
                        </>
                    )}
                    <FieldError message={errors.organization_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="payment-plan_id">Plan</Label>
                    {isLoadingOptions ? (
                        <Input
                            disabled
                            aria-label="Loading plans"
                            placeholder="Loading plans…"
                        />
                    ) : (
                        <>
                            <Select
                                value={planId}
                                onValueChange={setPlanId}
                            >
                                <SelectTrigger id="payment-plan_id">
                                    <SelectValue placeholder="Not linked" />
                                </SelectTrigger>
                                <SelectContent>
                                    {planOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="plan_id"
                                value={planId ?? ''}
                                readOnly
                            />
                        </>
                    )}
                    <FieldError message={errors.plan_id} />
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="payment-method">Method</Label>
                    <Input
                        id="payment-method"
                        name="method"
                        placeholder="mpesa, card, bank…"
                        defaultValue={payment?.method ?? ''}
                    />
                    <FieldError message={errors.method} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="payment-reference">Reference</Label>
                    <Input
                        id="payment-reference"
                        name="reference"
                        defaultValue={payment?.reference ?? ''}
                    />
                    <FieldError message={errors.reference} />
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="payment-period_start">
                        Period start
                    </Label>
                    <Input
                        id="payment-period_start"
                        name="period_start"
                        type="date"
                        defaultValue={payment?.period_start ?? ''}
                    />
                    <FieldError message={errors.period_start} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="payment-period_end">Period end</Label>
                    <Input
                        id="payment-period_end"
                        name="period_end"
                        type="date"
                        defaultValue={payment?.period_end ?? ''}
                    />
                    <FieldError message={errors.period_end} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payment-notes">Notes</Label>
                <Textarea
                    id="payment-notes"
                    name="notes"
                    rows={3}
                    defaultValue={payment?.notes ?? ''}
                />
                <FieldError message={errors.notes} />
            </div>
        </div>
    );
}

SubscriptionPaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        {
            title: 'Subscription payments',
            href: subscriptionPaymentRoutes.index().url,
        },
    ],
};

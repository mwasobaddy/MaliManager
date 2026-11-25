import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Check, HandCoins, Plus, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import * as refundRoutes from '@/routes/tenant/deposit-refunds';
import type {
    DepositRefund,
    LeaseOption,
    PaginatedRefunds,
} from '@/types/deposit-refund';

type Props = {
    refunds: PaginatedRefunds;
    focusedRefund: DepositRefund | null;
    leases: LeaseOption[];
    filters: { status: string; search: string };
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function DepositRefunds({
    refunds,
    focusedRefund,
    leases,
    filters,
}: Props) {
    const { query, setOverlayParam } = useOverlayParam();
    const { context } = usePage().props;
    const canDecide = (context?.permissions ?? []).includes('deposit.manage');

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'desc' : 'desc',
    };

    const isCreateOpen = query.get('create') === 'new';
    const viewedRefundId = Number(query.get('refund'));
    const viewedRefund =
        Number.isFinite(viewedRefundId) && viewedRefundId > 0
            ? (focusedRefund ??
              refunds.data.find((refund) => refund.id === viewedRefundId) ??
              null)
            : null;
    const isViewOpen = viewedRefund !== null && query.get('mode') === 'view';

    function openCreate() {
        setOverlayParam('create', 'new');
    }

    function closeSheets() {
        setOverlayParam('create', null, { replace: true });
        setOverlayParam('refund', null, { replace: true });
        setOverlayParam('mode', null, { replace: true });
    }

    function openView(refund: DepositRefund) {
        setOverlayParam('mode', 'view', {
            only: ['refunds', 'focusedRefund'],
            keep: { refund: String(refund.id) },
        });
    }

    const [isTableLoading, setIsTableLoading] = useState(false);

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(refundRoutes.index().url, params, {
            preserveScroll: true,
            replace: options?.replace ?? false,
            only: ['refunds'],
            onFinish: () => setIsTableLoading(false),
        });
    }

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

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

    function filterByStatus(value: string) {
        refetchTable(
            {
                search: filters.search,
                status: value === 'all' ? '' : value,
                sort: sort.column ?? '',
                direction: sort.direction,
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

    // Keep the box in step with Back/Forward.
    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const columns = useMemo<ColumnDef<DepositRefund, unknown>[]>(
        () => [
            {
                id: 'tenant',
                accessorKey: 'tenant_name',
                header: 'Occupant',
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <div className="font-medium">
                            {row.original.tenant_name ?? '—'}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            {[
                                row.original.unit_name,
                                row.original.property_name,
                            ]
                                .filter(Boolean)
                                .join(' · ') || row.original.reason}
                        </div>
                    </div>
                ),
            },
            {
                id: 'amount',
                accessorKey: 'amount_cents',
                header: 'Amount',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap tabular-nums">
                        {row.original.currency} {row.original.amount}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                enableSorting: false,
                cell: ({ row }) => (
                    <Badge variant={row.original.status_variant}>
                        {row.original.status_label}
                    </Badge>
                ),
            },
            {
                id: 'requested',
                accessorKey: 'created_at',
                header: 'Requested',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="text-xs text-muted-foreground">
                        <div>{row.original.requester_name ?? '—'}</div>
                        <div>{row.original.created_at?.slice(0, 10) ?? ''}</div>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <div
                        className="flex justify-end gap-1"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <ActionIconButton
                            icon={<HandCoins />}
                            label={`View refund for ${row.original.tenant_name ?? 'occupant'}`}
                            color="view"
                            onClick={() => openView(row.original)}
                        />
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Deposit refunds" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        title="Deposit refunds"
                        description="Return a deposit to an occupant. Money leaving the organization needs an approval before it is paid."
                    />
                    <LabelledIconButton
                        icon={<Plus />}
                        label="Request refund"
                        tooltip="Request a deposit refund"
                        color="primary"
                        onClick={openCreate}
                        className="ml-auto max-w-24 sm:max-w-28"
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
                                placeholder="Search occupant..."
                                aria-label="Search refunds"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <div className="ml-auto">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={filterByStatus}
                            >
                                <SelectTrigger
                                    className="w-40"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Pending
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Approved
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Rejected
                                    </SelectItem>
                                    <SelectItem value="paid">Paid</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={refunds.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(row) => openView(row)}
                        empty={
                            filters.search || filters.status
                                ? 'No refunds match your filters.'
                                : 'No refunds yet. Request one when an occupant leaves and the deposit has to be returned.'
                        }
                    />
                </Card>

                <Pagination
                    currentPage={refunds.current_page}
                    lastPage={refunds.last_page}
                    total={refunds.total}
                    label="refunds"
                    onChange={changePage}
                />
            </div>

            <RequestSheet
                open={isCreateOpen}
                leases={leases}
                onClose={closeSheets}
            />

            {viewedRefund && (
                <RefundSheet
                    refund={viewedRefund}
                    canDecide={canDecide}
                    onClose={closeSheets}
                />
            )}
        </>
    );
}

function RequestSheet({
    open,
    leases,
    onClose,
}: {
    open: boolean;
    leases: LeaseOption[];
    onClose: () => void;
}) {
    const [leaseId, setLeaseId] = useState('');

    const selectedLease = leases.find(
        (lease) => String(lease.value) === leaseId,
    );

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    onClose();
                }
            }}
            title="Request a refund"
            description="The amount cannot exceed the deposit held for the lease."
            footer={
                <OverlayFooter
                    onCancel={onClose}
                    actions={[
                        {
                            label: 'Request refund',
                            form: 'deposit-refund-form',
                            type: 'submit',
                        },
                    ]}
                />
            }
        >
            <Form
                id="deposit-refund-form"
                {...refundRoutes.store.form()}
                options={{ preserveScroll: true }}
                onSuccess={onClose}
                className="grid gap-4 py-2"
            >
                {({ errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="refund-lease">Lease</Label>
                            <SearchableSelect
                                id="refund-lease"
                                name="lease_id"
                                options={leases.map((lease) => ({
                                    value: String(lease.value),
                                    label: lease.label,
                                }))}
                                value={leaseId}
                                onValueChange={setLeaseId}
                                placeholder="Choose an active lease"
                                searchPlaceholder="Search leases…"
                            />
                            {selectedLease && (
                                <p className="text-xs text-muted-foreground">
                                    Deposit held:{' '}
                                    {selectedLease.deposit.toLocaleString()}
                                </p>
                            )}
                            <InputError message={errors.lease_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="refund-amount">Amount</Label>
                            <Input
                                id="refund-amount"
                                name="amount"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0.01"
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="refund-reason">Reason</Label>
                            <Textarea
                                id="refund-reason"
                                name="reason"
                                rows={3}
                                maxLength={2000}
                            />
                            <InputError message={errors.reason} />
                        </div>
                    </>
                )}
            </Form>
        </ResponsiveOverlay>
    );
}

function RefundSheet({
    refund,
    canDecide,
    onClose,
}: {
    refund: DepositRefund;
    canDecide: boolean;
    onClose: () => void;
}) {
    const [note, setNote] = useState('');
    const [reference, setReference] = useState('');

    return (
        <ResponsiveOverlay
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
            title={`Refund for ${refund.tenant_name ?? 'occupant'}`}
            description={`${refund.currency} ${refund.amount} · ${refund.status_label}`}
        >
            <div className="grid gap-4 py-2">
                <dl className="grid gap-3 text-sm">
                    <div className="grid grid-cols-[7rem_1fr] gap-2">
                        <dt className="text-muted-foreground">Unit</dt>
                        <dd>
                            {[refund.unit_name, refund.property_name]
                                .filter(Boolean)
                                .join(' · ') || '—'}
                        </dd>
                    </div>
                    <div className="grid grid-cols-[7rem_1fr] gap-2">
                        <dt className="text-muted-foreground">Reason</dt>
                        <dd className="min-w-0 break-words">{refund.reason}</dd>
                    </div>
                    <div className="grid grid-cols-[7rem_1fr] gap-2">
                        <dt className="text-muted-foreground">Requested</dt>
                        <dd>
                            {refund.requester_name ?? '—'}
                            {refund.created_at
                                ? ` · ${refund.created_at.slice(0, 10)}`
                                : ''}
                        </dd>
                    </div>
                    {refund.status !== 'pending' && (
                        <>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    Decision
                                </dt>
                                <dd>
                                    {refund.status_label}
                                    {refund.decider_name
                                        ? ` by ${refund.decider_name}`
                                        : ''}
                                    {refund.decided_at
                                        ? ` · ${refund.decided_at.slice(0, 10)}`
                                        : ''}
                                </dd>
                            </div>
                            {refund.decision_note && (
                                <div className="grid grid-cols-[7rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        Note
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {refund.decision_note}
                                    </dd>
                                </div>
                            )}
                        </>
                    )}
                    {refund.status === 'paid' && (
                        <>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">Paid</dt>
                                <dd>
                                    {refund.payer_name ?? '—'}
                                    {refund.paid_at
                                        ? ` · ${refund.paid_at.slice(0, 10)}`
                                        : ''}
                                </dd>
                            </div>
                            {refund.paid_reference && (
                                <div className="grid grid-cols-[7rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        Reference
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {refund.paid_reference}
                                    </dd>
                                </div>
                            )}
                        </>
                    )}
                </dl>

                {refund.status === 'pending' && canDecide && (
                    <div className="grid gap-2 rounded-lg border border-border-strong p-3">
                        <Label htmlFor="refund-decision-note">
                            Decision note (optional)
                        </Label>
                        <Textarea
                            id="refund-decision-note"
                            rows={2}
                            maxLength={2000}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                        />
                        <div className="flex gap-2">
                            <Form
                                {...refundRoutes.update.form(refund.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={onClose}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="decision"
                                            value="approve"
                                        />
                                        <input
                                            type="hidden"
                                            name="note"
                                            value={note}
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <Check className="size-4" />
                                            Approve
                                        </Button>
                                        <InputError message={errors.decision} />
                                    </>
                                )}
                            </Form>
                            <Form
                                {...refundRoutes.update.form(refund.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={onClose}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="decision"
                                            value="reject"
                                        />
                                        <input
                                            type="hidden"
                                            name="note"
                                            value={note}
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <X className="size-4" />
                                            Reject
                                        </Button>
                                        <InputError message={errors.decision} />
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>
                )}

                {refund.status === 'approved' && canDecide && (
                    <Form
                        {...refundRoutes.pay.form(refund.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="grid gap-2 rounded-lg border border-border-strong p-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Label htmlFor="refund-paid-reference">
                                    Payment reference (optional)
                                </Label>
                                <Input
                                    id="refund-paid-reference"
                                    name="reference"
                                    placeholder="M-Pesa CODE123"
                                    value={reference}
                                    onChange={(event) =>
                                        setReference(event.target.value)
                                    }
                                />
                                <InputError message={errors.reference} />
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        <HandCoins className="size-4" />
                                        Mark as paid
                                    </Button>
                                </div>
                                <InputError message={errors.paid} />
                            </>
                        )}
                    </Form>
                )}
            </div>
        </ResponsiveOverlay>
    );
}

DepositRefunds.layout = {
    breadcrumbs: [{ title: 'Deposit refunds' }],
};

import { Head, Link, router, usePage } from '@inertiajs/react';
import { SquarePen, Printer, Receipt, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty } from '@/components/ui/empty';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format-date';
import { formatMoney } from '@/lib/format-money';
import {
    index as paymentsIndexRoute,
    create as paymentsCreateRoute,
    edit as paymentsEditRoute,
    show as paymentsShowRoute,
    destroy as destroyPayment,
} from '@/routes/tenant/payments';
import { bulk as trashBulk } from '@/routes/tenant/trash';

type PaymentRow = {
    id: number;
    tenant_name: string | null;
    property_name: string | null;
    unit_name: string | null;
    amount: string | null;
    currency: string | null;
    paid_on: string | null;
    method: string | null;
    status: string | null;
    reference: string | null;
};

type Props = {
    payments: {
        data: PaymentRow[];
        current_page: number;
        last_page: number;
    };
    statuses: string[];
};

export default function PaymentsIndex({ payments, statuses }: Props) {
    const [status, setStatus] = useState('');

    const { context } = usePage().props;
    const canDelete = (context?.permissions ?? []).includes('payment.manage');

    const [deleteTarget, setDeleteTarget] = useState<PaymentRow | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const pageIds = payments.data.map((payment) => payment.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

    // The delete overlay is local state, so it closes itself once the
    // fresh props come back without the row. A failed confirm (wrong
    // password) keeps the row — and the overlay — put.
    useEffect(() => {
        if (
            deleteTarget !== null &&
            !payments.data.some((payment) => payment.id === deleteTarget.id)
        ) {
            setDeleteTarget(null);
        }
    }, [payments.data, deleteTarget]);

    // Selection never outlives the page: a refetch that drops a row also
    // drops its tick, so the bulk payload can only name visible rows.
    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                payments.data.some((payment) => payment.id === id),
            ),
        );
    }, [payments.data]);

    const applyFilter = (value: string) => {
        setStatus(value);
        router.get(
            paymentsIndexRoute().url,
            { status: value === '' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        title="Payments"
                        description="Rent payments recorded against leases."
                    />
                    <div className="flex items-center gap-2">
                        <Select
                            value={status || 'all'}
                            onValueChange={applyFilter}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem
                                        key={s}
                                        value={s}
                                        className="capitalize"
                                    >
                                        {s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Record payment"
                            tooltip="Record rent received from a tenant"
                            color="primary"
                            href={paymentsCreateRoute().url}
                            data-test="new-payment-button"
                            className="ml-auto max-w-20 sm:max-w-24"
                        />
                    </div>
                </div>

                <BulkDeleteBar
                    pageIds={pageIds}
                    selectedIds={selectedIds}
                    onSelectionChange={setSelectedIds}
                    onDelete={() => setBulkOpen(true)}
                    onClear={() => setSelectedIds([])}
                    canDelete={canDelete}
                    noun="payments"
                />

                {payments.data.length === 0 ? (
                    <Empty
                        icon={<Receipt className="size-4" />}
                        title="No payments recorded yet"
                        description="Recorded rent payments will appear here."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    {canDelete && (
                                        <th className="w-8 px-3 py-2">
                                            <Checkbox
                                                checked={
                                                    allSelected
                                                        ? true
                                                        : selectedIds.length ===
                                                            0
                                                          ? false
                                                          : 'indeterminate'
                                                }
                                                onCheckedChange={() =>
                                                    setSelectedIds(
                                                        allSelected
                                                            ? []
                                                            : [...pageIds],
                                                    )
                                                }
                                                aria-label="Select all payments on this page"
                                            />
                                        </th>
                                    )}
                                    <th className="px-3 py-2">Tenant</th>
                                    <th className="px-3 py-2">Property</th>
                                    <th className="px-3 py-2">Unit</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2 text-right">
                                        Method
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Reference
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Receipt
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Edit
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((payment) => (
                                    <tr
                                        key={payment.id}
                                        className={
                                            selectedIds.includes(payment.id)
                                                ? 'border-b bg-muted/60 last:border-0'
                                                : 'border-b last:border-0'
                                        }
                                    >
                                        {canDelete && (
                                            <td className="px-3 py-2">
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        payment.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedIds(
                                                            (current) =>
                                                                checked === true
                                                                    ? [
                                                                          ...current,
                                                                          payment.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              payment.id,
                                                                      ),
                                                        )
                                                    }
                                                    aria-label={`Select payment ${payment.tenant_name ?? payment.unit_name ?? payment.id}`}
                                                />
                                            </td>
                                        )}
                                        <td className="px-3 py-2 font-medium">
                                            {payment.tenant_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {payment.property_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {payment.unit_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {payment.paid_on
                                                ? formatDate(payment.paid_on)
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right capitalize">
                                            {payment.method}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {payment.reference ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <a
                                                href={
                                                    paymentsShowRoute({
                                                        payment: payment.id,
                                                    }).url
                                                }
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex text-primary"
                                                aria-label="View receipt"
                                            >
                                                <Printer className="size-4" />
                                            </a>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Badge
                                                variant={
                                                    payment.status ===
                                                    'received'
                                                        ? 'success'
                                                        : payment.status ===
                                                            'pending'
                                                          ? 'warning'
                                                          : 'danger'
                                                }
                                                className="capitalize"
                                            >
                                                {payment.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            paymentsEditRoute({
                                                                payment:
                                                                    payment.id,
                                                            }).url
                                                        }
                                                        aria-label={`Edit payment for ${payment.tenant_name ?? payment.unit_name ?? `payment ${payment.id}`}`}
                                                    >
                                                        <SquarePen className="size-4" />
                                                    </Link>
                                                </Button>
                                                {canDelete && (
                                                    <ActionIconButton
                                                        icon={<Trash2 />}
                                                        label={`Delete payment for ${payment.tenant_name ?? payment.unit_name ?? `payment ${payment.id}`}`}
                                                        color="danger"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                payment,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination
                    currentPage={payments.current_page}
                    lastPage={payments.last_page}
                    label="payments"
                    onChange={(page) =>
                        router.get(
                            paymentsIndexRoute().url,
                            {
                                page,
                                status: status === '' ? undefined : status,
                            },
                            { preserveScroll: true },
                        )
                    }
                />

                {canDelete && deleteTarget !== null && (
                    <ConfirmDeleteOverlay
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setDeleteTarget(null);
                            }
                        }}
                        action={destroyPayment(deleteTarget.id).url}
                        title={`Delete payment for ${deleteTarget.tenant_name ?? deleteTarget.unit_name ?? `payment ${deleteTarget.id}`}?`}
                        description="This payment moves to trash, where it can be restored or permanently deleted."
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
                        payload={{ module: 'payments', ids: selectedIds }}
                        title={
                            selectedIds.length === 1
                                ? 'Delete this payment?'
                                : `Delete ${selectedIds.length} payments?`
                        }
                        description="They move to trash, where they can be restored or permanently deleted."
                        submitLabel={
                            selectedIds.length === 1
                                ? 'Delete payment'
                                : `Delete ${selectedIds.length} payments`
                        }
                        processingLabel="Deleting…"
                        onSuccess={() => {
                            setSelectedIds([]);
                            setBulkOpen(false);
                        }}
                    />
                )}
            </div>
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [{ title: 'Payments' }],
};

import { Head, router, useForm } from '@inertiajs/react';
import { Check, HandCoins, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
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
import * as refundRoutes from '@/routes/tenant/deposit-refunds';
import type {
    DepositRefund,
    DepositRefundFormData,
    LeaseOption,
    PaginatedRefunds,
} from '@/types/deposit-refund';

type Props = {
    refunds: PaginatedRefunds;
    leases: LeaseOption[];
    filters: { status: string };
};

/**
 * Deposit refunds.
 *
 * The request form mirrors `StoreDepositRefundRequest`: a lease from this
 * organization, an amount above zero, and a reason. The server additionally
 * checks the amount against the lease's deposit and refuses a second open
 * refund — both are facts about the data, which the browser does not have.
 */
export default function DepositRefunds({ refunds, leases, filters }: Props) {
    const [deciding, setDeciding] = useState<number | null>(null);

    const form = useForm<DepositRefundFormData>({
        lease_id: '',
        amount: '',
        reason: '',
    });

    const selectedLease = leases.find(
        (lease) => String(lease.value) === form.data.lease_id,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(refundRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const decide = (refund: DepositRefund, approve: boolean) => {
        setDeciding(refund.id);
        router.patch(
            refundRoutes.update.url({ refund: refund.id }),
            { decision: approve ? 'approve' : 'reject' },
            {
                preserveScroll: true,
                onFinish: () => setDeciding(null),
            },
        );
    };

    const filterByStatus = (value: string) => {
        router.get(
            refundRoutes.index.url({
                query: { status: value === 'all' ? undefined : value },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Deposit refunds" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Deposit refunds"
                    description="Return a deposit to an occupant. Money leaving the organization needs an approval before it is paid."
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Request a refund
                            </CardTitle>
                            <CardDescription>
                                The amount cannot exceed the deposit held for
                                the lease.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="lease_id">Lease</Label>
                                    <Select
                                        value={form.data.lease_id}
                                        onValueChange={(value) =>
                                            form.setData('lease_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="lease_id"
                                            aria-invalid={
                                                !!form.errors.lease_id
                                            }
                                        >
                                            <SelectValue placeholder="Choose an active lease" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {leases.map((lease) => (
                                                <SelectItem
                                                    key={lease.value}
                                                    value={String(lease.value)}
                                                >
                                                    {lease.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedLease && (
                                        <p className="text-muted-foreground text-xs">
                                            Deposit held:{' '}
                                            {selectedLease.deposit.toLocaleString()}
                                        </p>
                                    )}
                                    {form.errors.lease_id && (
                                        <p className="text-status-danger text-sm">
                                            {form.errors.lease_id}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        value={form.data.amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'amount',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors.amount}
                                    />
                                    {form.errors.amount && (
                                        <p className="text-status-danger text-sm">
                                            {form.errors.amount}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <Textarea
                                        id="reason"
                                        rows={3}
                                        maxLength={2000}
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors.reason}
                                    />
                                    {form.errors.reason && (
                                        <p className="text-status-danger text-sm">
                                            {form.errors.reason}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    Request refund
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                            <div>
                                <CardTitle className="text-base font-medium">
                                    Refunds
                                </CardTitle>
                                <CardDescription>
                                    {refunds.total.toLocaleString()} total
                                </CardDescription>
                            </div>

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
                        </CardHeader>

                        <CardContent className="px-0">
                            {refunds.data.length === 0 ? (
                                <Empty
                                    icon={<HandCoins className="size-4" />}
                                    title="No refunds"
                                    description="Request one when an occupant leaves and the deposit has to be returned."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-border-strong border-b">
                                                {[
                                                    'Occupant',
                                                    'Amount',
                                                    'Status',
                                                    'Requested by',
                                                    'Actions',
                                                ].map((heading) => (
                                                    <th
                                                        key={heading}
                                                        scope="col"
                                                        className="text-muted-foreground bg-bg-base px-3 py-2 text-left text-xs font-medium tracking-wide uppercase last:text-right"
                                                    >
                                                        {heading}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {refunds.data.map((refund) => (
                                                <tr
                                                    key={refund.id}
                                                    className="border-border/60 hover:bg-bg-base border-b last:border-b-0"
                                                >
                                                    <td className="px-3 py-2">
                                                        <div>
                                                            {refund.tenant_name ??
                                                                '—'}
                                                        </div>
                                                        <div className="text-muted-foreground max-w-72 truncate text-xs">
                                                            {refund.reason}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {refund.currency}{' '}
                                                        {refund.amount}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Badge
                                                            variant={
                                                                refund.status_variant
                                                            }
                                                        >
                                                            {refund.status_label}
                                                        </Badge>
                                                    </td>
                                                    <td className="text-muted-foreground px-3 py-2">
                                                        {refund.requester_name ??
                                                            '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <div className="flex justify-end gap-2">
                                                            {refund.status ===
                                                            'pending' ? (
                                                                <>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-status-success gap-1"
                                                                        disabled={
                                                                            deciding ===
                                                                            refund.id
                                                                        }
                                                                        onClick={() =>
                                                                            decide(
                                                                                refund,
                                                                                true,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Check className="size-4" />
                                                                        Approve
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-status-danger gap-1"
                                                                        disabled={
                                                                            deciding ===
                                                                            refund.id
                                                                        }
                                                                        onClick={() =>
                                                                            decide(
                                                                                refund,
                                                                                false,
                                                                            )
                                                                        }
                                                                    >
                                                                        <X className="size-4" />
                                                                        Reject
                                                                    </Button>
                                                                </>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs">
                                                                    {refund.decider_name
                                                                        ? `by ${refund.decider_name}`
                                                                        : '—'}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>

                        {refunds.last_page > 1 && (
                            <div className="px-4 pb-4">
                                <Pagination
                                    currentPage={refunds.current_page}
                                    lastPage={refunds.last_page}
                                    total={refunds.total}
                                    label="refunds"
                                    onChange={(page) =>
                                        router.get(
                                            refundRoutes.index.url({
                                                query: {
                                                    page,
                                                    status:
                                                        filters.status ||
                                                        undefined,
                                                },
                                            }),
                                            {},
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                />
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

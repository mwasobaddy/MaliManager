import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Printer } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as paymentsIndexRoute,
    create as paymentsCreateRoute,
    edit as paymentsEditRoute,
    destroy as paymentsDestroyRoute,
    show as paymentsShowRoute,
} from '@/routes/tenant/payments';

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

export default function PaymentsIndex({
    payments,
    statuses,
}: Props) {
    const [status, setStatus] = useState('');

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
                        <Button asChild data-test="new-payment-button">
                            <Link href={paymentsCreateRoute().url}>
                                <Plus className="size-4" />
                                Record payment
                            </Link>
                        </Button>
                    </div>
                </div>

                {payments.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No payments recorded yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-2">Tenant</th>
                                    <th className="px-3 py-2">Property</th>
                                    <th className="px-3 py-2">Unit</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2 text-right">Method</th>
                                    <th className="px-3 py-2 text-right">Reference</th>
                                    <th className="px-3 py-2 text-right">Receipt</th>
                                    <th className="px-3 py-2 text-right">Status</th>
                                    <th className="px-3 py-2 text-right">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((payment) => (
                                    <tr
                                        key={payment.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {payment.tenant_name}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {payment.property_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {payment.unit_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {payment.amount} {payment.currency}
                                        </td>
                                        <td className="px-3 py-2">
                                            {payment.paid_on}
                                        </td>
                                        <td className="px-3 py-2 text-right capitalize">
                                            {payment.method}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {payment.reference ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <a
                                                href={paymentsShowRoute({ payment: payment.id }).url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex text-primary"
                                                aria-label="View receipt"
                                            >
                                                <Printer className="size-4" />
                                            </a>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <span
                                                className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ${
                                                    payment.status === 'received'
                                                        ? 'bg-green-100 text-green-800'
                                                        : payment.status === 'pending'
                                                            ? 'bg-yellow-100 text-yellow-800'
                                                            : 'bg-red-100 text-red-800'
                                                }`}
                                            >
                                                {payment.status}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={paymentsEditRoute({ payment: payment.id }).url}
                                                    aria-label={`Edit payment for ${payment.tenant_name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {payments.last_page > 1 && (
                    <div className="flex gap-1 text-sm">
                        {Array.from(
                            { length: payments.last_page },
                            (_, i) => i + 1,
                        ).map((page) => (
                            <Button
                                key={page}
                                variant={
                                    page === payments.current_page
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        paymentsIndexRoute().url,
                                        {
                                            page,
                                            status: status === '' ? undefined : status,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

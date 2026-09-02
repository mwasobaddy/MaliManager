import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import * as subscriptionPaymentRoutes from '@/routes/platform/subscription-payments';

type PaymentRow = {
    id: number;
    organization: { id: number; name: string } | null;
    plan: { id: number; name: string } | null;
    amount: number;
    currency: string;
    received_on: string;
    method: string | null;
    reference: string | null;
};

type Props = {
    payments: {
        data: PaymentRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
    };
};

export default function SubscriptionPaymentsIndex({
    payments,
    filters,
}: Props) {
    const authPermissions = (usePage().props.auth?.permissions ??
        []) as string[];
    const canCreate = authPermissions.includes('subscription.create');
    const canDelete = authPermissions.includes('subscription.delete');
    const [deleteTarget, setDeleteTarget] = useState<PaymentRow | null>(null);

    const form = useForm({ search: filters.search });

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            subscriptionPaymentRoutes.index().url,
            { search: form.data.search },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Subscription payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Subscription payments"
                        description="Income received from organizations for their subscriptions."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={subscriptionPaymentRoutes.create()}>
                                <Plus className="size-4" />
                                New payment
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="p-3">
                        <Form onSubmit={submitSearch} className="flex gap-2">
                            {({ processing }) => (
                                <>
                                    <div className="relative flex-1">
                                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            name="search"
                                            value={form.data.search}
                                            onChange={(e) =>
                                                form.setData(
                                                    'search',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Search reference, method or organization"
                                            className="pl-8"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Search
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">
                                            Organization
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Plan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Amount
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Received
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Method
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Reference
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No subscription payments found.
                                            </td>
                                        </tr>
                                    )}
                                    {payments.data.map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="border-b"
                                        >
                                            <td className="px-3 py-2">
                                                {payment.organization?.name ??
                                                    '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {payment.plan?.name ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 font-medium">
                                                {payment.currency}{' '}
                                                {Number(
                                                    payment.amount,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="px-3 py-2">
                                                {payment.received_on}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge variant="secondary">
                                                    {payment.method ?? '—'}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {payment.reference ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={subscriptionPaymentRoutes.edit(
                                                                payment.id,
                                                            )}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    payment,
                                                                )
                                                            }
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {payments.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {payments.current_page} of {payments.last_page}{' '}
                            ({payments.total} payments)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={payments.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        subscriptionPaymentRoutes.index().url,
                                        {
                                            search: filters.search,
                                            page: payments.current_page - 1,
                                        },
                                        { preserveState: true },
                                    )
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
                                    router.get(
                                        subscriptionPaymentRoutes.index().url,
                                        {
                                            search: filters.search,
                                            page: payments.current_page + 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <PasswordConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                formAction={subscriptionPaymentRoutes.destroy.form(
                    deleteTarget?.id ?? 0,
                )}
                title={`Delete payment from ${deleteTarget?.organization?.name ?? 'this organization'}?`}
                description="This soft-deletes the subscription payment record. Please enter your password to confirm."
            />
        </>
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

import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    index as paymentsIndexRoute,
    store as storePaymentRoute,
    update as updatePaymentRoute,
} from '@/routes/tenant/payments';

type LeaseOption = {
    id: number;
    name: string;
};

type Payment = {
    id: number;
    lease_id: number;
    amount: string | number | null;
    currency: string | null;
    paid_on: string | null;
    period_start: string | null;
    period_end: string | null;
    method: string | null;
    reference: string | null;
    status: string | null;
    notes: string | null;
};

type Props = {
    payment: Payment | null;
    leases: LeaseOption[];
};

export default function PaymentForm({ payment, leases }: Props) {
    const form = useForm<{
        lease_id: string;
        amount: string;
        currency: string;
        paid_on: string;
        period_start: string;
        period_end: string;
        method: string;
        reference: string;
        status: string;
        notes: string;
    }>({
        lease_id: payment?.lease_id ? String(payment.lease_id) : '',
        amount: payment?.amount != null ? String(payment.amount) : '',
        currency: payment?.currency ?? 'KES',
        paid_on: payment?.paid_on ?? new Date().toISOString().slice(0, 10),
        period_start: payment?.period_start ?? '',
        period_end: payment?.period_end ?? '',
        method: payment?.method ?? 'cash',
        reference: payment?.reference ?? '',
        status: payment?.status ?? 'received',
        notes: payment?.notes ?? '',
    });

    const submit = () => {
        if (payment) {
            form.put(updatePaymentRoute({ payment: payment.id }).url);
        } else {
            form.post(storePaymentRoute().url);
        }
    };

    return (
        <>
            <Head title={payment ? 'Edit payment' : 'Record payment'} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title={payment ? 'Edit payment' : 'Record payment'}
                    description="Rent payment against a lease — amount, method, period and receipt."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Payment details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Lease</Label>
                            <Select
                                value={form.data.lease_id}
                                onValueChange={(v) =>
                                    form.setData('lease_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pick a lease…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {leases.map((l) => (
                                        <SelectItem
                                            key={l.id}
                                            value={String(l.id)}
                                        >
                                            {l.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.lease_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Amount</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                            />
                            <InputError message={form.errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Currency</Label>
                            <Input
                                maxLength={3}
                                value={form.data.currency}
                                onChange={(e) =>
                                    form.setData(
                                        'currency',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.currency} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Paid on</Label>
                            <Input
                                type="date"
                                value={form.data.paid_on}
                                onChange={(e) =>
                                    form.setData('paid_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.paid_on} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Period start (optional)</Label>
                            <Input
                                type="date"
                                value={form.data.period_start}
                                onChange={(e) =>
                                    form.setData('period_start', e.target.value)
                                }
                            />
                            <InputError message={form.errors.period_start} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Period end (optional)</Label>
                            <Input
                                type="date"
                                value={form.data.period_end}
                                onChange={(e) =>
                                    form.setData('period_end', e.target.value)
                                }
                            />
                            <InputError message={form.errors.period_end} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Method</Label>
                            <Select
                                value={form.data.method}
                                onValueChange={(v) =>
                                    form.setData('method', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {['cash', 'mpesa', 'bank', 'cheque', 'card', 'other'].map(
                                        (m) => (
                                            <SelectItem
                                                key={m}
                                                value={m}
                                                className="capitalize"
                                            >
                                                {m}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.method} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Reference</Label>
                            <Input
                                value={form.data.reference}
                                onChange={(e) =>
                                    form.setData('reference', e.target.value)
                                }
                            />
                            <InputError message={form.errors.reference} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(v) =>
                                    form.setData('status', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {['received', 'pending', 'refunded'].map((s) => (
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
                            <InputError message={form.errors.status} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label>Notes</Label>
                            <textarea
                                rows={3}
                                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={paymentsIndexRoute().url}>
                            Back to payments
                        </Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        {payment ? 'Save changes' : 'Record payment'}
                    </Button>
                </div>
            </div>
        </>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
import * as subscriptionPaymentRoutes from '@/routes/platform/subscription-payments';

type Option = {
    value: number;
    label: string;
};

type PaymentData = {
    organization_id: string;
    plan_id: string;
    amount: string;
    currency: string;
    received_on: string;
    period_start: string;
    period_end: string;
    method: string;
    reference: string;
    notes: string;
};

type Payment = {
    id: number;
    organization_id: number | null;
    plan_id: number | null;
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
    payment?: Payment;
    organizations?: Option[];
    plans?: Option[];
};

export default function SubscriptionPaymentForm({
    payment,
    organizations = [],
    plans = [],
}: Props) {
    const isEdit = !!payment;

    const form = useForm<PaymentData>({
        organization_id:
            payment?.organization_id != null
                ? String(payment.organization_id)
                : '',
        plan_id: payment?.plan_id != null ? String(payment.plan_id) : '',
        amount: payment?.amount != null ? String(payment.amount) : '',
        currency: payment?.currency ?? 'KES',
        received_on: payment?.received_on ?? '',
        period_start: payment?.period_start ?? '',
        period_end: payment?.period_end ?? '',
        method: payment?.method ?? '',
        reference: payment?.reference ?? '',
        notes: payment?.notes ?? '',
    });

    form.transform((data) => ({
        ...data,
        organization_id:
            data.organization_id === '' ? null : Number(data.organization_id),
        plan_id: data.plan_id === '' ? null : Number(data.plan_id),
        amount: Number(data.amount),
        period_start: data.period_start === '' ? null : data.period_start,
        period_end: data.period_end === '' ? null : data.period_end,
        method: data.method === '' ? null : data.method,
        reference: data.reference === '' ? null : data.reference,
        notes: data.notes === '' ? null : data.notes,
    }));

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit && payment) {
            form.patch(subscriptionPaymentRoutes.update(payment.id).url);
        } else {
            form.post(subscriptionPaymentRoutes.store().url);
        }
    }

    return (
        <>
            <Head
                title={
                    isEdit
                        ? 'Edit subscription payment'
                        : 'New subscription payment'
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={subscriptionPaymentRoutes.index().url}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={
                            isEdit
                                ? 'Edit subscription payment'
                                : 'New subscription payment'
                        }
                        description="Record income received from an organization for their subscription."
                    />
                </div>

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Subscription</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="organization_id">
                                    Organization
                                </Label>
                                <Select
                                    value={form.data.organization_id}
                                    onValueChange={(v) =>
                                        form.setData('organization_id', v)
                                    }
                                >
                                    <SelectTrigger id="organization_id">
                                        <SelectValue placeholder="Select an organization" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {organizations.map((org) => (
                                            <SelectItem
                                                key={org.value}
                                                value={String(org.value)}
                                            >
                                                {org.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.organization_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="plan_id">Plan</Label>
                                <Select
                                    value={form.data.plan_id}
                                    onValueChange={(v) =>
                                        form.setData('plan_id', v)
                                    }
                                >
                                    <SelectTrigger id="plan_id">
                                        <SelectValue placeholder="Select a plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((plan) => (
                                            <SelectItem
                                                key={plan.value}
                                                value={String(plan.value)}
                                            >
                                                {plan.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.plan_id} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payment</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        value={form.data.amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input
                                        id="currency"
                                        name="currency"
                                        maxLength={3}
                                        value={form.data.currency}
                                        onChange={(e) =>
                                            form.setData(
                                                'currency',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.currency}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="received_on">Received on</Label>
                                <Input
                                    id="received_on"
                                    name="received_on"
                                    type="date"
                                    value={form.data.received_on}
                                    onChange={(e) =>
                                        form.setData(
                                            'received_on',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.received_on} />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="period_start">
                                        Period start
                                    </Label>
                                    <Input
                                        id="period_start"
                                        name="period_start"
                                        type="date"
                                        value={form.data.period_start}
                                        onChange={(e) =>
                                            form.setData(
                                                'period_start',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.period_start}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="period_end">
                                        Period end
                                    </Label>
                                    <Input
                                        id="period_end"
                                        name="period_end"
                                        type="date"
                                        value={form.data.period_end}
                                        onChange={(e) =>
                                            form.setData(
                                                'period_end',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.period_end}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="method">Method</Label>
                                    <Input
                                        id="method"
                                        name="method"
                                        value={form.data.method}
                                        onChange={(e) =>
                                            form.setData(
                                                'method',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="mpesa, card, bank…"
                                    />
                                    <InputError message={form.errors.method} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        name="reference"
                                        value={form.data.reference}
                                        onChange={(e) =>
                                            form.setData(
                                                'reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.reference}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={form.errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2 lg:col-span-2">
                        <Button variant="outline" asChild>
                            <Link href={subscriptionPaymentRoutes.index().url}>
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Save changes' : 'Record payment'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

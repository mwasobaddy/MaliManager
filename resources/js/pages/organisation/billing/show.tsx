import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/tenant/billing';
import { card as payByCard, mpesa as payByMpesa } from '@/routes/tenant/billing';

type BillingStatus = {
    plan: {
        name: string;
        price: number;
        currency: string;
    } | null;
    on_trial: boolean;
    trial_ends_at: string | null;
    subscribed: boolean;
    subscription_ends_at: string | null;
    paid_access: boolean;
    pending_attempt: {
        gateway: string;
        created_at: string;
    } | null;
};

type Props = {
    organization: {
        id: number;
        name: string;
        slug: string;
    };
    billing: BillingStatus;
};

function formatPrice(price: number, currency: string) {
    return `${price.toLocaleString()} ${currency} / month`;
}

export default function BillingShow({
    organization,
    billing,
}: Props) {
    const [phone, setPhone] = useState('');
    const payable = billing.plan !== null && billing.plan.price > 0;

    return (
        <>
            <Head title="Billing" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    level="h1"
                    title="Billing"
                    description={`Manage ${organization.name}'s subscription.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">
                            Subscription
                        </CardTitle>
                        <CardDescription>
                            {billing.plan
                                ? `You are on the ${billing.plan.name} plan.`
                                : 'No plan attached yet.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {billing.plan && billing.plan.price > 0 && (
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Price
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatPrice(
                                        billing.plan.price,
                                        billing.plan.currency,
                                    )}
                                </span>
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">
                                Free trial
                            </span>
                            <span className="font-medium">
                                {billing.on_trial
                                    ? `Active until ${billing.trial_ends_at}`
                                    : billing.trial_ends_at
                                      ? `Ended ${billing.trial_ends_at}`
                                      : 'Not started'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">
                                Subscription
                            </span>
                            <span className="font-medium">
                                {billing.subscribed
                                    ? `Active until ${billing.subscription_ends_at}`
                                    : 'None — pay below to activate'}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {payable ? (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base font-medium">
                                    Pay with M-Pesa
                                </CardTitle>
                                <CardDescription>
                                    An STK push is sent to your phone — enter
                                    your M-Pesa PIN to complete payment.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {billing.pending_attempt?.gateway ===
                                'mpesa' ? (
                                    <p className="text-sm text-muted-foreground">
                                        A payment request is already on its
                                        way — check your phone for the M-Pesa
                                        prompt.
                                    </p>
                                ) : (
                                    <Form
                                        {...payByMpesa.form()}
                                        className="grid gap-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="phone">
                                                        M-Pesa phone number
                                                    </Label>
                                                    <Input
                                                        id="phone"
                                                        name="phone"
                                                        type="tel"
                                                        required
                                                        autoComplete="tel"
                                                        placeholder="0712 345 678"
                                                        value={phone}
                                                        onChange={(e) =>
                                                            setPhone(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors.phone}
                                                    />
                                                </div>
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        {processing
                                                            ? 'Sending…'
                                                            : `Pay ${formatPrice(billing.plan!.price, billing.plan!.currency)}`}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base font-medium">
                                    Pay with card
                                </CardTitle>
                                <CardDescription>
                                    Visa, Mastercard and other cards via a
                                    secure hosted checkout.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {billing.pending_attempt?.gateway ===
                                'paystack' ? (
                                    <p className="text-sm text-muted-foreground">
                                        A card checkout is already open —
                                        complete it in the checkout tab, or
                                        start a fresh one below.
                                    </p>
                                ) : null}
                                <Form {...payByCard.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Starting…'
                                                : `Pay ${formatPrice(billing.plan!.price, billing.plan!.currency)} with card`}
                                        </Button>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">
                                You are on the Free plan — there is nothing to
                                pay. An owner can move you to a paid plan from
                                the platform side.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

BillingShow.layout = {
    breadcrumbs: [
        {
            title: 'Billing',
            href: show(),
        },
    ],
};

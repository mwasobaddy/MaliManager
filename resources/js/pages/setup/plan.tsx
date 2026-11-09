import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
import type { SetupProgress } from '@/components/setup-steps';
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
import { Spinner } from '@/components/ui/spinner';
import { firstAsset } from '@/routes/onboarding';
import { store as confirmPlan } from '@/routes/onboarding/plan';

type Plan = {
    name: string;
    slug: string;
    description: string;
    price: number;
    currency: string;
    price_label: string;
};

type Props = {
    organization: {
        id: number;
        name: string;
    };
    plans: Plan[];
    user: {
        phone: string | null;
        notification_channel: string;
    };
    setupProgress: SetupProgress;
};

/**
 * Step 3, Plan & trial: confirm the plan (moved out of step 1 so the
 * choice meets the user after they have committed) with first-month
 * framing, and pick how billing reminders should arrive. Nothing is
 * charged here — paid plans start their free month on submit.
 */
export default function SetupPlan({
    organization,
    plans,
    user,
    setupProgress,
}: Props) {
    const [planSlug, setPlanSlug] = useState(plans[0]?.slug ?? '');
    const [channel, setChannel] = useState(
        user.notification_channel === 'sms' ? 'sms' : 'email',
    );
    const [phone, setPhone] = useState(user.phone ?? '');

    const paidFrom = new Date();
    paidFrom.setMonth(paidFrom.getMonth() + 1);
    const paidFromLabel = paidFrom.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return (
        <>
            <Head title="Pick your plan" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        'Your asset is in place',
                        'Pick a plan — the first month is free',
                    ]}
                    current={3}
                    progress={setupProgress}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={3} progress={setupProgress} />

                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            level="h1"
                            title="Pick your plan"
                            description={`Choose ${organization.name}'s plan. Paid plans start with a free month — nothing is charged today.`}
                        />
                        <Button asChild variant="outline" size="sm">
                            <Link href={firstAsset.url()}>
                                <ArrowLeft className="size-4" />
                                Back
                            </Link>
                        </Button>
                    </div>

                    <Form
                        {...confirmPlan.form()}
                        className="flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base font-medium">
                                            Plan
                                        </CardTitle>
                                        <CardDescription>
                                            You can change this later from
                                            Billing.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <input
                                            type="hidden"
                                            name="plan_slug"
                                            value={planSlug}
                                        />
                                        <div
                                            className="grid gap-3"
                                            role="radiogroup"
                                            aria-label="Plan"
                                        >
                                            {plans.map((plan) => (
                                                <button
                                                    key={plan.slug}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={
                                                        planSlug === plan.slug
                                                    }
                                                    onClick={() =>
                                                        setPlanSlug(plan.slug)
                                                    }
                                                    className={`rounded-lg border p-4 text-left transition-colors ${
                                                        planSlug === plan.slug
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-input hover:bg-muted'
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">
                                                            {plan.name}
                                                        </span>
                                                        {plan.price === 0 ? (
                                                            <span className="text-sm text-muted-foreground">
                                                                {
                                                                    plan.price_label
                                                                }
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm">
                                                                <s className="text-muted-foreground">
                                                                    {
                                                                        plan.price_label
                                                                    }
                                                                </s>{' '}
                                                                <span className="font-medium">
                                                                    KES 0 first
                                                                    month
                                                                </span>
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {plan.description}
                                                    </p>
                                                    {plan.price > 0 &&
                                                        planSlug ===
                                                            plan.slug && (
                                                            <p className="mt-2 text-sm font-medium">
                                                                Then{' '}
                                                                {
                                                                    plan.price_label
                                                                }{' '}
                                                                a month from{' '}
                                                                {paidFromLabel}.
                                                            </p>
                                                        )}
                                                </button>
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors.plan_slug}
                                        />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base font-medium">
                                            Billing reminders
                                        </CardTitle>
                                        <CardDescription>
                                            Trial-expiry and payment reminders
                                            arrive on this channel — yours,
                                            and later your clients' own
                                            choices.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <input
                                            type="hidden"
                                            name="notification_channel"
                                            value={channel}
                                        />
                                        <div
                                            className="grid gap-3"
                                            role="radiogroup"
                                            aria-label="Reminder channel"
                                        >
                                            {(
                                                [
                                                    {
                                                        value: 'email',
                                                        title: 'Email',
                                                        hint: 'Reminders land in your inbox.',
                                                    },
                                                    {
                                                        value: 'sms',
                                                        title: 'SMS',
                                                        hint: 'Reminders arrive as texts on your phone.',
                                                    },
                                                ] as const
                                            ).map((option) => (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={
                                                        channel ===
                                                        option.value
                                                    }
                                                    onClick={() =>
                                                        setChannel(
                                                            option.value,
                                                        )
                                                    }
                                                    className={`rounded-lg border p-4 text-left transition-colors ${
                                                        channel ===
                                                        option.value
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-input hover:bg-muted'
                                                    }`}
                                                >
                                                    <span className="font-medium">
                                                        {option.title}
                                                    </span>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {option.hint}
                                                    </p>
                                                </button>
                                            ))}
                                        </div>
                                        <InputError
                                            message={
                                                errors.notification_channel
                                            }
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">
                                                Phone number
                                                {channel === 'sms'
                                                    ? ''
                                                    : ' (optional)'}
                                            </Label>
                                            <Input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                autoComplete="tel"
                                                placeholder="+254 712 345 678"
                                                value={phone}
                                                onChange={(e) =>
                                                    setPhone(e.target.value)
                                                }
                                            />
                                            <InputError
                                                message={errors.phone}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing || planSlug === ''}
                                >
                                    {processing && <Spinner />}
                                    Confirm plan
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { complete } from '@/routes/onboarding';

type Props = {
    user: {
        name: string;
        email: string;
        phone: string | null;
    };
    hasOrganization: boolean;
    organization: {
        id: number;
        name: string;
        currency: string;
    } | null;
    plans: Plan[];
};

type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string;
    price: number;
    currency: string;
    properties_limit: number | null;
    units_limit: number | null;
    price_label: string;
};

type AccountType = 'organization' | 'occupant';

const currencies = ['KES', 'USD', 'UGX', 'TZS', 'RWF', 'NGN', 'GBP', 'EUR'];

export default function Onboarding({ user, hasOrganization, organization, plans }: Props) {
    const [accountType, setAccountType] = useState<AccountType>(
        hasOrganization ? 'organization' : 'occupant',
    );
    const [currency, setCurrency] = useState(organization?.currency ?? 'KES');
    const [planSlug, setPlanSlug] = useState(
        hasOrganization ? plans[0]?.slug ?? '' : '',
    );

    const isOrganization = hasOrganization || accountType === 'organization';
    const steps = hasOrganization ? ['profile', 'organization', 'plan'] : isOrganization ? ['account-type', 'profile', 'organization', 'plan'] : ['account-type', 'profile'];
    const [index, setIndex] = useState(0);
    const step = steps[index];

    const canProceed =
        step === 'account-type' ||
        step === 'profile' ||
        step === 'organization' ||
        (step === 'plan' && planSlug !== '');

    return (
        <>
            <Head title="Welcome" />

            <div className="space-y-6">
                <div className="space-y-1">
                    <h2 className="text-lg font-medium">
                        Welcome{user.name ? `, ${user.name.split(' ')[0]}` : ''}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Let's get your account set up.
                    </p>
                </div>

                <ol className="flex items-center gap-1 text-xs text-muted-foreground">
                    {steps.map((s, i) => (
                        <li key={s} className="flex items-center gap-1">
                            <span
                                className={`flex size-5 items-center justify-center rounded-full ${
                                    i < index
                                        ? 'bg-primary text-primary-foreground'
                                        : i === index
                                          ? 'border border-primary text-foreground'
                                          : 'border border-input text-muted-foreground'
                                }`}
                            >
                                {i + 1}
                            </span>
                            {i < steps.length - 1 && <span className="h-px w-4 bg-border" />}
                        </li>
                    ))}
                </ol>

                <Form {...complete.form()} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            {step === 'account-type' && (
                                <div className="grid gap-3">
                                    <input type="hidden" name="account_type" value={accountType} />
                                    {([
                                        ['occupant', 'I rent a property', "I'm an occupant looking to manage my rental."],
                                        ['organization', 'I own or manage properties', "I'm a landlord or property manager."],
                                    ] as [AccountType, string, string][]).map(
                                        ([value, title, description]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => setAccountType(value)}
                                                className={`rounded-lg border p-4 text-left transition-colors ${
                                                    accountType === value
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-input hover:bg-muted'
                                                }`}
                                            >
                                                <span className="font-medium">{title}</span>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {description}
                                                </p>
                                            </button>
                                        ),
                                    )}
                                </div>
                            )}

                            {step === 'profile' && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Your full name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            type="text"
                                            required
                                            defaultValue={user.name}
                                            autoComplete="name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone number (optional)</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            defaultValue={user.phone ?? ''}
                                            autoComplete="tel"
                                            placeholder="+254 712 345 678"
                                        />
                                        <InputError message={errors.phone} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError message={errors.password_confirmation} />
                                    </div>
                                </>
                            )}

                            {step === 'organization' && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="organization_name">
                                            Organization name
                                        </Label>
                                        <Input
                                            id="organization_name"
                                            name="organization_name"
                                            type="text"
                                            required
                                            defaultValue={organization?.name ?? ''}
                                            autoComplete="organization"
                                        />
                                        <InputError message={errors.organization_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">Default currency</Label>
                                        <input type="hidden" name="currency" value={currency} />
                                        <Select value={currency} onValueChange={setCurrency}>
                                            <SelectTrigger id="currency">
                                                <SelectValue placeholder="Select currency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {currencies.map((code) => (
                                                    <SelectItem key={code} value={code}>
                                                        {code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.currency} />
                                    </div>
                                </>
                            )}

                            {step === 'plan' && (
                                <>
                                    <input type="hidden" name="plan_slug" value={planSlug} />
                                    <div className="grid gap-3">
                                        {plans.map((plan) => (
                                            <button
                                                key={plan.id}
                                                type="button"
                                                onClick={() => setPlanSlug(plan.slug)}
                                                className={`rounded-lg border p-4 text-left transition-colors ${
                                                    planSlug === plan.slug
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-input hover:bg-muted'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="font-medium">{plan.name}</span>
                                                    <span className="text-sm text-muted-foreground">
                                                        {plan.price_label}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {plan.description}
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                </>
                            )}

                            <div className="flex gap-3">
                                {index > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => setIndex(index - 1)}
                                    >
                                        Back
                                    </Button>
                                )}
                                {index < steps.length - 1 ? (
                                    <Button
                                        type="button"
                                        className="w-full"
                                        disabled={!canProceed}
                                        onClick={() => setIndex(index + 1)}
                                    >
                                        Continue
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing || !canProceed}
                                    >
                                        {processing && <Spinner />}
                                        {isOrganization ? 'Finish setup' : 'Complete'}
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Onboarding.layout = {
    title: 'Get started',
    description: 'A few details to finish setting up your account',
};
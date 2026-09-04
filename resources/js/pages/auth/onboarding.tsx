import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type AccountType = 'organization' | 'searcher';

type FieldErrors = Partial<
    Record<
        | 'name'
        | 'phone'
        | 'password'
        | 'password_confirmation'
        | 'organization_name',
        string
    >
>;

const currencies = ['KES', 'USD', 'UGX', 'TZS', 'RWF', 'NGN', 'GBP', 'EUR'];

export default function Onboarding({
    user,
    hasOrganization,
    organization,
    plans,
}: Props) {
    const [accountType, setAccountType] = useState<AccountType>(
        hasOrganization ? 'organization' : 'searcher',
    );
    const [name, setName] = useState(user.name);
    const [phone, setPhone] = useState(user.phone ?? '');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [organizationName, setOrganizationName] = useState(
        organization?.name ?? '',
    );
    const [currency, setCurrency] = useState(organization?.currency ?? 'KES');
    const [planSlug, setPlanSlug] = useState(
        hasOrganization ? (plans[0]?.slug ?? '') : '',
    );
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const [touched, setTouched] = useState<Record<string, boolean>>({});

    const isOrganization = hasOrganization || accountType === 'organization';
    const steps = hasOrganization
        ? ['profile', 'organization', 'plan']
        : isOrganization
          ? ['account-type', 'profile', 'organization', 'plan']
          : ['account-type', 'profile'];
    const [index, setIndex] = useState(0);
    const step = steps[index];

    const validateField = (
        field: keyof FieldErrors,
        value: string,
    ): string | undefined => {
        switch (field) {
            case 'name':
                return value.trim() === ''
                    ? 'Your full name is required.'
                    : undefined;
            case 'phone':
                return value.trim() !== '' && value.length > 20
                    ? 'Phone number must be 20 characters or fewer.'
                    : undefined;
            case 'password':
                if (value === '') {
                    return 'A password is required.';
                }

                if (value.length < 8) {
                    return 'Password must be at least 8 characters.';
                }

                return undefined;
            case 'password_confirmation':
                return value !== password
                    ? 'Passwords do not match.'
                    : undefined;
            case 'organization_name':
                return value.trim() === ''
                    ? 'Organization name is required.'
                    : undefined;
        }
    };

    const validateStep = (): FieldErrors => {
        const nextErrors: FieldErrors = {};

        if (step === 'profile') {
            nextErrors.name = validateField('name', name);
            nextErrors.phone = validateField('phone', phone);
            nextErrors.password = validateField('password', password);
            nextErrors.password_confirmation = validateField(
                'password_confirmation',
                passwordConfirmation,
            );
        }

        if (step === 'organization') {
            nextErrors.organization_name = validateField(
                'organization_name',
                organizationName,
            );
        }

        return Object.fromEntries(
            Object.entries(nextErrors).filter(
                ([, message]) => message !== undefined,
            ),
        ) as FieldErrors;
    };

    const stepValid = (() => {
        if (step === 'account-type') {
            return true;
        }

        if (step === 'profile') {
            return (
                name.trim() !== '' &&
                validateField('phone', phone) === undefined &&
                password.length >= 8 &&
                passwordConfirmation === password
            );
        }

        if (step === 'organization') {
            return organizationName.trim() !== '';
        }

        return step === 'plan' && planSlug !== '';
    })();

    const handleBlur = (field: keyof FieldErrors, value: string) => {
        setTouched((prev) => ({ ...prev, [field]: true }));
        setFieldErrors((prev) => ({
            ...prev,
            [field]: validateField(field, value),
        }));
    };

    const handleNext = () => {
        setFieldErrors(validateStep());
        setIndex(index + 1);
    };

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
                            {i < steps.length - 1 && (
                                <span className="h-px w-4 bg-border" />
                            )}
                        </li>
                    ))}
                </ol>

                <Form
                    {...complete.form()}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div hidden={step !== 'account-type'}>
                                <input
                                    type="hidden"
                                    name="account_type"
                                    value={accountType}
                                />
                                <div className="grid gap-3">
                                    {(
                                        [
                                            [
                                                'searcher',
                                                'I rent a property',
                                                "I'm an occupant looking to manage my rental.",
                                            ],
                                            [
                                                'organization',
                                                'I own or manage properties',
                                                "I'm a landlord or property manager.",
                                            ],
                                        ] as [AccountType, string, string][]
                                    ).map(([value, title, description]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() =>
                                                setAccountType(value)
                                            }
                                            className={`rounded-lg border p-4 text-left transition-colors ${
                                                accountType === value
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-input hover:bg-muted'
                                            }`}
                                        >
                                            <span className="font-medium">
                                                {title}
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {description}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div
                                hidden={step !== 'profile'}
                                className="grid gap-6"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Your full name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        autoComplete="name"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        onBlur={() => handleBlur('name', name)}
                                    />
                                    {touched.name && fieldErrors.name ? (
                                        <p
                                            className="text-sm text-destructive"
                                            data-test="name-invalid"
                                        >
                                            {fieldErrors.name}
                                        </p>
                                    ) : (
                                        <InputError message={errors.name} />
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        Phone number (optional)
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
                                        onBlur={() =>
                                            handleBlur('phone', phone)
                                        }
                                    />
                                    {touched.phone && fieldErrors.phone ? (
                                        <p
                                            className="text-sm text-destructive"
                                            data-test="phone-invalid"
                                        >
                                            {fieldErrors.phone}
                                        </p>
                                    ) : (
                                        <InputError message={errors.phone} />
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        value={password}
                                        onChange={(e) =>
                                            setPassword(e.target.value)
                                        }
                                        onBlur={() =>
                                            handleBlur('password', password)
                                        }
                                    />
                                    {touched.password &&
                                    fieldErrors.password ? (
                                        <p
                                            className="text-sm text-destructive"
                                            data-test="password-invalid"
                                        >
                                            {fieldErrors.password}
                                        </p>
                                    ) : (
                                        <InputError message={errors.password} />
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        value={passwordConfirmation}
                                        onChange={(e) =>
                                            setPasswordConfirmation(
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            handleBlur(
                                                'password_confirmation',
                                                passwordConfirmation,
                                            )
                                        }
                                    />
                                    {touched.password_confirmation &&
                                    fieldErrors.password_confirmation ? (
                                        <p
                                            className="text-sm text-destructive"
                                            data-test="password-confirmation-invalid"
                                        >
                                            {fieldErrors.password_confirmation}
                                        </p>
                                    ) : (
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    )}
                                </div>
                            </div>

                            <div
                                hidden={step !== 'organization'}
                                className="grid gap-6"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="organization_name">
                                        Organization name
                                    </Label>
                                    <Input
                                        id="organization_name"
                                        name="organization_name"
                                        type="text"
                                        required
                                        autoComplete="organization"
                                        value={organizationName}
                                        onChange={(e) =>
                                            setOrganizationName(e.target.value)
                                        }
                                        onBlur={() =>
                                            handleBlur(
                                                'organization_name',
                                                organizationName,
                                            )
                                        }
                                    />
                                    {touched.organization_name &&
                                    fieldErrors.organization_name ? (
                                        <p
                                            className="text-sm text-destructive"
                                            data-test="organization-name-invalid"
                                        >
                                            {fieldErrors.organization_name}
                                        </p>
                                    ) : (
                                        <InputError
                                            message={errors.organization_name}
                                        />
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="currency">
                                        Default currency
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="currency"
                                        value={currency}
                                    />
                                    <Select
                                        value={currency}
                                        onValueChange={setCurrency}
                                    >
                                        <SelectTrigger id="currency">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((code) => (
                                                <SelectItem
                                                    key={code}
                                                    value={code}
                                                >
                                                    {code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.currency} />
                                </div>
                            </div>

                            <div hidden={step !== 'plan'}>
                                <input
                                    type="hidden"
                                    name="plan_slug"
                                    value={planSlug}
                                />
                                <div className="grid gap-3">
                                    {plans.map((plan) => (
                                        <button
                                            key={plan.id}
                                            type="button"
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
                            </div>

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
                                        disabled={!stepValid}
                                        onClick={handleNext}
                                    >
                                        Continue
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing || !stepValid}
                                    >
                                        {processing && <Spinner />}
                                        {isOrganization
                                            ? 'Finish setup'
                                            : 'Complete'}
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

import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
};

type FieldErrors = Partial<
    Record<'name' | 'phone' | 'password' | 'password_confirmation', string>
>;

/**
 * Guest-minimal onboarding: name, phone and password. No account-type choice,
 * no organization details, no plan — becoming a landlord happens later from
 * the guest home, and the tenant identity is created on first tenant action.
 */
export default function Onboarding({ user }: Props) {
    const [name, setName] = useState(user.name);
    const [phone, setPhone] = useState(user.phone ?? '');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const [touched, setTouched] = useState<Record<string, boolean>>({});

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
        }
    };

    const formValid =
        name.trim() !== '' &&
        validateField('phone', phone) === undefined &&
        password.length >= 8 &&
        passwordConfirmation === password;

    const handleBlur = (field: keyof FieldErrors, value: string) => {
        setTouched((prev) => ({ ...prev, [field]: true }));
        setFieldErrors((prev) => ({
            ...prev,
            [field]: validateField(field, value),
        }));
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
                        A few details to finish setting up your account. You
                        can list a property or rent one later.
                    </p>
                </div>

                <Form
                    {...complete.form()}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
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
                                        <p className="text-sm text-destructive">
                                            {
                                                fieldErrors.password_confirmation
                                            }
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

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing || !formValid}
                            >
                                {processing && <Spinner />}
                                Complete
                            </Button>
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

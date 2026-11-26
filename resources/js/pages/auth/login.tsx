import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { GoogleIcon } from '@/components/google-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { redirect as googleRedirect } from '@/routes/auth/socialite';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const phonePattern = /^[+\d][\d\s\-().]{7,}$/;

export default function Login({ status }: Props) {
    const [identifier, setIdentifier] = useState('');
    const [remember, setRemember] = useState(false);
    const [touched, setTouched] = useState(false);

    const trimmed = identifier.trim();
    const identifierValid =
        emailPattern.test(trimmed) || phonePattern.test(trimmed);

    return (
        <>
            <Head title="Log in" />

            {/* Passkeys disabled.
            <PasskeyVerify />
            */}

            <Button variant="outline" className="w-full" asChild>
                <a href={googleRedirect.url('google')}>
                    <GoogleIcon />
                    Continue with Google
                </a>
            </Button>

            <div className="relative my-6 text-center text-sm">
                <span className="absolute inset-x-0 top-1/2 border-t border-border" />
                <span className="relative z-10 bg-card px-3 text-muted-foreground">
                    Or continue with email or phone
                </span>
            </div>

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="identifier">
                                    Email address or phone number
                                </Label>
                                <Input
                                    id="identifier"
                                    type="text"
                                    name="identifier"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    placeholder="email@example.com or 0712 000 000"
                                    value={identifier}
                                    onChange={(e) =>
                                        setIdentifier(e.target.value)
                                    }
                                    onBlur={() => setTouched(true)}
                                    aria-invalid={touched && !identifierValid}
                                    aria-describedby={
                                        touched && !identifierValid
                                            ? 'identifier-hint'
                                            : undefined
                                    }
                                />
                                {touched && !identifierValid ? (
                                    <p
                                        id="identifier-hint"
                                        role="alert"
                                        className="text-sm text-destructive"
                                        data-test="identifier-invalid"
                                    >
                                        Enter a valid email address or phone
                                        number.
                                    </p>
                                ) : (
                                    <InputError message={errors.identifier} />
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={2}
                                disabled={processing || !identifierValid}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Continue
                            </Button>

                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id="remember"
                                    checked={remember}
                                    onCheckedChange={(checked) =>
                                        setRemember(checked === true)
                                    }
                                />
                                <input
                                    type="hidden"
                                    name="remember"
                                    value={remember ? '1' : '0'}
                                />
                                <div>
                                    <Label
                                        htmlFor="remember"
                                        className="cursor-pointer text-sm font-medium"
                                    >
                                        Stay signed in on this phone
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Keeps you signed in for 30 days on this
                                        device. Don&apos;t use this on a shared
                                        phone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-status-success">
                    {status}
                </div>
            )}

            <p className="mt-2 text-center text-sm text-muted-foreground">
                We&apos;ll send you a one-time code to sign in.
            </p>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email or phone number to get a one-time code',
};

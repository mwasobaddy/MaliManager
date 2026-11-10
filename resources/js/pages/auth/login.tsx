import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { GoogleIcon } from '@/components/google-icon';
import InputError from '@/components/input-error';
// Passkeys disabled.
// import PasskeyVerify from '@/components/passkey-verify';
import { Button } from '@/components/ui/button';
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

export default function Login({ status }: Props) {
    const [email, setEmail] = useState('');
    const [touched, setTouched] = useState(false);

    const emailValid = emailPattern.test(email);

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
                    Or continue with email
                </span>
            </div>

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    onBlur={() => setTouched(true)}
                                    aria-invalid={touched && !emailValid}
                                    aria-describedby={
                                        touched && !emailValid
                                            ? 'email-hint'
                                            : undefined
                                    }
                                />
                                {touched && !emailValid ? (
                                    <p
                                        id="email-hint"
                                        role="alert"
                                        className="text-sm text-destructive"
                                        data-test="email-invalid"
                                    >
                                        Please enter a valid email address.
                                    </p>
                                ) : (
                                    <InputError message={errors.email} />
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={2}
                                disabled={processing || !emailValid}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Continue
                            </Button>
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
                We'll email you a one-time code to sign in.
            </p>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and we will send you a one-time code',
};

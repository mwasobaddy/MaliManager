import { Form, Head } from '@inertiajs/react';
import { GoogleIcon } from '@/components/google-icon';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
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

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <a href={googleRedirect.url('google')} className="w-full">
                <Button type="button" variant="outline" className="w-full" tabIndex={0}>
                    <GoogleIcon />
                    Continue with Google
                </Button>
            </a>

            <div className="relative text-center text-sm">
                <span className="relative z-10 bg-sidebar px-2 text-muted-foreground">or</span>
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
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={2}
                                disabled={processing}
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
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <p className="text-center text-sm text-muted-foreground">
                We'll email you a one-time code to sign in.
            </p>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and we will send you a one-time code',
};
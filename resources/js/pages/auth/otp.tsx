import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { resend, verify } from '@/routes/login/otp';

type Props = {
    email: string;
    status?: string;
};

export default function Otp({ email, status }: Props) {
    const [code, setCode] = useState('');

    return (
        <>
            <Head title="Enter your code" />

            <div className="space-y-6">
                <div className="space-y-1">
                    <h2 className="text-lg font-medium">Check your email</h2>
                    <p className="text-sm text-muted-foreground">
                        We sent a one-time code to <strong>{email}</strong>.
                    </p>
                </div>

                <Form {...verify.form()} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="code">One-time code</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    required
                                    autoFocus
                                    maxLength={6}
                                    value={code}
                                    onChange={(e) =>
                                        setCode(
                                            e.target.value.replace(
                                                /[^0-9]/g,
                                                '',
                                            ),
                                        )
                                    }
                                    placeholder="123456"
                                    className="text-center text-lg tracking-widest"
                                />
                                {code.length > 0 && code.length < 6 ? (
                                    <p
                                        className="text-sm text-destructive"
                                        data-test="code-incomplete"
                                    >
                                        Code must be 6 digits.
                                    </p>
                                ) : (
                                    <InputError message={errors.code} />
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing || code.length !== 6}
                                data-test="verify-button"
                            >
                                {processing && <Spinner />}
                                Verify
                            </Button>
                        </>
                    )}
                </Form>

                {status && (
                    <div className="text-center text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}

                <div className="flex items-center justify-between text-sm">
                    <Form {...resend.form()} className="inline">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="link"
                                className="h-auto p-0"
                                disabled={processing}
                                data-test="resend-button"
                            >
                                {processing ? 'Sending…' : 'Resend code'}
                            </Button>
                        )}
                    </Form>
                    <Link
                        href={login()}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        Use a different email
                    </Link>
                </div>
            </div>
        </>
    );
}

Otp.layout = {
    title: 'Enter your code',
    description: 'Verify your email to continue',
};

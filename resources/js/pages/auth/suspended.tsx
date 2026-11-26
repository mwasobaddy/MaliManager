import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { store as storeReactivation } from '@/routes/reactivation';

type Props = {
    supportEmail?: string;
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export default function Suspended({ supportEmail }: Props) {
    const [email, setEmail] = useState('');

    const emailValid = emailPattern.test(email);

    return (
        <>
            <Head title="Account suspended" />

            <div className="space-y-6 text-center">
                <p className="text-sm text-muted-foreground">
                    Your account has been suspended. If you believe this is a
                    mistake, request a review below
                    {supportEmail ? (
                        <>
                            {' '}
                            or contact us at{' '}
                            <a
                                href={`mailto:${supportEmail}`}
                                className="font-medium text-primary underline"
                            >
                                {supportEmail}
                            </a>
                        </>
                    ) : null}
                    .
                </p>

                <Form
                    {...storeReactivation.form()}
                    className="space-y-4 text-left"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing || !emailValid}
                            >
                                {processing && <Spinner />}
                                Request reactivation
                            </Button>
                        </>
                    )}
                </Form>

                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Log out
                </Link>
            </div>
        </>
    );
}

Suspended.layout = {
    title: 'Account suspended',
    description: 'Contact your administrator if you believe this is an error.',
};

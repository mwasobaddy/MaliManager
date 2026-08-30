import { Head, Link } from '@inertiajs/react';
import { logout } from '@/routes';

export default function Suspended() {
    return (
        <>
            <Head title="Account suspended" />

            <div className="space-y-6 text-center">
                <p className="text-sm text-muted-foreground">
                    Your account has been suspended. Please contact your
                    administrator if you believe this is an error.
                </p>

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
    description: 'You have been signed out.',
};

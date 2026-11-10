import { Head, Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';

type Props = {
    status: number;
    title: string;
    description: string;
};

/**
 * Branded error page for full-page (non-Inertia) failures. Inertia visits
 * keep the toast-and-back behaviour in bootstrap/app.php — replacing live app
 * state with an error page on every 403 would strand the user, so these
 * components only ever render on a fresh page load.
 */
export default function ErrorPage({ status, title, description }: Props) {
    return (
        <>
            <Head title={`${status} — ${title}`} />

            <div className="flex min-h-screen flex-col bg-background font-sans text-foreground antialiased">
                <header className="border-b border-border">
                    <div className="mx-auto flex h-18 max-w-7xl items-center px-6 lg:px-10">
                        <Link
                            href="/"
                            aria-label="MaliManager home"
                            className="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <AppLogo />
                        </Link>
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-xl flex-1 flex-col items-center justify-center px-6 py-20 text-center">
                    <p className="font-display text-7xl font-semibold tracking-tight text-brand-primary-600">
                        {status}
                    </p>
                    <h1 className="mt-4 text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="mt-3 leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild>
                            <Link href="/">Back to home</Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            Go back
                        </Button>
                    </div>
                </main>

                <footer className="border-t border-border py-6 text-center text-sm text-muted-foreground">
                    &copy; {new Date().getFullYear()} MaliManager. All rights
                    reserved.
                </footer>
            </div>
        </>
    );
}

import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';

export default function TenantPickerLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 items-center gap-2 border-b px-6">
                <Link href={home()} className="flex items-center gap-2">
                    <AppLogo />
                </Link>
            </header>
            <main className="flex flex-1 items-start justify-center px-6 py-10">
                <div className="w-full max-w-4xl">{children}</div>
            </main>
        </div>
    );
}
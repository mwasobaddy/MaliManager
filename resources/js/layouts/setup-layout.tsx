import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes/guest';

/**
 * Minimal shell for one-time setup steps (become a landlord, first asset).
 * Funnel pages strip app navigation — the sidebar is distraction plus exit
 * points mid-conversion — while the auth split-card would squeeze choice
 * cards into a narrow form column beside a decorative brand panel. So: a
 * slim header with the logo as the only way out (the escape hatch research
 * says trapped users abandon), centered content, no chrome.
 */
export default function SetupLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 shrink-0 items-center px-4 md:px-6">
                <Link
                    href={home.url()}
                    className="flex items-center gap-2 rounded-md"
                    aria-label="MaliManager home"
                >
                    <AppLogo />
                </Link>
            </header>
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4 md:p-6">
                {children}
            </main>
            <footer className="px-4 py-6 text-center text-xs text-muted-foreground md:px-6">
                © {new Date().getFullYear()} MaliManager. All rights reserved.
            </footer>
        </div>
    );
}

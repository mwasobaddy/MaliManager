import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { home } from '@/routes/guest';

/**
 * Minimal shell for one-time setup steps (become a landlord, first asset).
 * Funnel pages strip app navigation — the sidebar is distraction plus exit
 * points mid-conversion — while the auth split-card would squeeze choice
 * cards into a narrow form column beside a decorative brand panel. So: a
 * slim header, centered content, no chrome. The logo and the Back button
 * are the only way out (trapped users abandon instead of converting), and
 * both land on home, from where the funnel re-opens with state intact.
 */
export default function SetupLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 shrink-0 items-center px-4 md:px-6">
                <div className="grid w-full grid-cols-[1fr_auto_1fr] items-center">
                    <div className="flex justify-start">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={home.url()}>
                                <ArrowLeft className="size-4" />
                                Back
                            </Link>
                        </Button>
                    </div>
                    <Link
                        href={home.url()}
                        className="flex items-center gap-2 rounded-md"
                        aria-label="MaliManager home"
                    >
                        <AppLogo />
                    </Link>
                    <span aria-hidden />
                </div>
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

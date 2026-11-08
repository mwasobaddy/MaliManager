import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';

/**
 * Minimal shell for one-time setup steps (first asset). The auth split-card
 * squeezes these choices into a narrow form column beside a decorative brand
 * panel; the app sidebar oversells navigation the user cannot use yet
 * (every property link bounces off the first-property guard). So: logo,
 * centered content, no chrome.
 */
export default function SetupLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 shrink-0 items-center px-4 md:px-6">
                <span className="flex items-center gap-2">
                    <AppLogo />
                </span>
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

import type { ReactNode } from 'react';

/**
 * Minimal shell for one-time setup steps (become a landlord, first asset).
 * Funnel pages strip app navigation — the sidebar is distraction plus exit
 * points mid-conversion — while the auth split-card would squeeze choice
 * cards into a narrow form column beside a decorative brand panel. So: no
 * header chrome, centered content, no distractions. Each step owns its own
 * back navigation next to its heading, pointing at the previous funnel
 * step (or home from the first step).
 */
export default function SetupLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col">
                {children}
            </main>
        </div>
    );
}

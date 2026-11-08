import type { LucideIcon } from 'lucide-react';
import { SetupSteps } from '@/components/setup-steps';

export type SetupPoint = {
    icon: LucideIcon;
    text: string;
};

/**
 * The funnel's side panel, following the register-page inspo: a brand
 * gradient card with the welcome copy, the step indicator, and the page's
 * value props. Desktop only — on phones the same steps render as a compact
 * top card so the form stays first on screen.
 */
export function SetupSidePanel({
    title,
    description,
    current,
    points = [],
}: {
    title: string;
    description: string;
    current: 1 | 2;
    points?: SetupPoint[];
}) {
    return (
        <div className="hidden lg:block">
            <div className="rounded-xl border bg-gradient-to-b from-brand-primary-100 via-card to-brand-accent-100 p-8">
                <p className="text-xl font-semibold tracking-tight text-foreground">
                    {title}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
                {points.length > 0 && (
                    <ul className="mt-6 space-y-3">
                        {points.map((point) => (
                            <li
                                key={point.text}
                                className="flex items-center gap-2.5 text-sm"
                            >
                                <point.icon
                                    className="size-4 shrink-0 text-brand-accent"
                                    aria-hidden
                                />
                                <span className="text-foreground">
                                    {point.text}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="mt-8">
                    <SetupSteps current={current} direction="vertical" />
                </div>
            </div>
        </div>
    );
}

/**
 * The same steps as a compact top card for phones, where the side panel is
 * hidden so the form comes first.
 */
export function SetupStepsTopCard({ current }: { current: 1 | 2 }) {
    return (
        <div className="rounded-xl border bg-card p-4 lg:hidden">
            <SetupSteps current={current} />
        </div>
    );
}

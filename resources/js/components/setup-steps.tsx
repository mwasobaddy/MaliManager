import { Building2, Check, House } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type SetupStep = {
    title: string;
    description: string;
    icon: LucideIcon;
};

/**
 * The landlord funnel, in order. One list feeds both the desktop side panel
 * and the mobile stepper so the two can never disagree about what comes next.
 */
export const SETUP_STEPS: SetupStep[] = [
    {
        title: 'Set up organization',
        description: 'Name, currency and plan',
        icon: Building2,
    },
    {
        title: 'Add your first asset',
        description: 'A building or a land parcel',
        icon: House,
    },
];

/**
 * Compact horizontal stepper for phones, where the side panel is hidden.
 * Text labels, not just dots: someone arriving mid-flow should read what is
 * next rather than decode a position.
 */
export function SetupSteps({ current }: { current: 1 | 2 }) {
    return (
        <ol className="flex items-center gap-2" aria-label="Setup progress">
            {SETUP_STEPS.map((step, index) => {
                const number = index + 1;
                const done = number < current;
                const active = number === current;

                return (
                    <li
                        key={step.title}
                        aria-current={active ? 'step' : undefined}
                        className="flex min-w-0 items-center gap-2"
                    >
                        {index > 0 && (
                            <span
                                aria-hidden
                                className="h-px w-6 shrink-0 bg-border"
                            />
                        )}
                        <span
                            aria-hidden
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                                done && 'bg-brand-accent text-on-accent',
                                active &&
                                    'bg-brand-accent-100 text-accent-text',
                                !done &&
                                    !active &&
                                    'bg-muted text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : number}
                        </span>
                        <span
                            className={cn(
                                'truncate text-sm',
                                active
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {step.title}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

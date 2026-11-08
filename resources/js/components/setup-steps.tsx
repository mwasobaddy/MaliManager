import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const STEPS = ['Set up organization', 'Add your first asset'];

/**
 * Where the landlord funnel stands. Text labels, not just dots: a user who
 * lands here mid-flow should read what comes next, not decode a position.
 */
export function SetupSteps({ current }: { current: 1 | 2 }) {
    return (
        <ol className="flex items-center gap-2" aria-label="Setup progress">
            {STEPS.map((label, index) => {
                const step = index + 1;
                const done = step < current;
                const active = step === current;

                return (
                    <li
                        key={label}
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
                            {done ? <Check className="size-3.5" /> : step}
                        </span>
                        <span
                            className={cn(
                                'truncate text-sm',
                                active
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

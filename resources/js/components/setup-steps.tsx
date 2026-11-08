import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const STEPS = ['Set up organization', 'Add your first asset'];

const SUBTITLES = ['Name, currency, plan', 'Property or land parcel'];

/**
 * Where the landlord funnel stands. Text labels, not just dots: a user who
 * lands here mid-flow should read what comes next, not decode a position.
 */
export function SetupSteps({
    current,
    direction = 'horizontal',
}: {
    current: 1 | 2;
    direction?: 'horizontal' | 'vertical';
}) {
    const vertical = direction === 'vertical';

    return (
        <ol
            className={vertical ? 'space-y-5' : 'flex items-center gap-2'}
            aria-label="Setup progress"
        >
            {STEPS.map((label, index) => {
                const step = index + 1;
                const done = step < current;
                const active = step === current;

                const badge = (
                    <span
                        aria-hidden
                        className={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                            done && 'bg-brand-accent text-on-accent',
                            active && 'bg-brand-accent-100 text-accent-text',
                            !done &&
                                !active &&
                                'bg-muted text-muted-foreground',
                        )}
                    >
                        {done ? <Check className="size-3.5" /> : step}
                    </span>
                );

                if (!vertical) {
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
                            {badge}
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
                }

                return (
                    <li key={label} className="flex items-start gap-3">
                        <span
                            aria-hidden
                            className="flex flex-col items-center self-stretch"
                        >
                            {badge}
                            {index < STEPS.length - 1 && (
                                <span className="w-px flex-1 bg-border" />
                            )}
                        </span>
                        <span
                            className="min-w-0 pb-1"
                            aria-current={active ? 'step' : undefined}
                        >
                            <span
                                className={cn(
                                    'block text-sm',
                                    active
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {label}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {SUBTITLES[index]}
                            </span>
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

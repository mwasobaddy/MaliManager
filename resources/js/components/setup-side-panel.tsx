import { BadgeCheck, CheckCircle2 } from 'lucide-react';
import {
    SETUP_STEPS,
    SetupStepTitle,
    SetupSteps,
    type SetupProgress,
} from '@/components/setup-steps';
import { cn } from '@/lib/utils';

/**
 * The vertical step list for the left card: a 48px circle per step joined by
 * a connector, with the title and description beside it. Mirrors the register
 * page's LeftStepIndicator, including the completed halo, the dashed upcoming
 * connector and the 64px gap under each step.
 */
function SetupLeftIndicator({
    current,
    progress,
}: {
    current: 1 | 2 | 3 | 4 | 5;
    progress: SetupProgress;
}) {
    return (
        <div className="mb-8">
            <div className="mx-auto flex max-w-3xl flex-col">
                {SETUP_STEPS.map((step, index) => {
                    const Icon = step.icon;
                    const number = index + 1;
                    const isActive = current === number;
                    const isCompleted = current > number;

                    return (
                        <div key={step.title} className="flex gap-2">
                            <div className="flex flex-col items-center">
                                <div
                                    className={cn(
                                        isCompleted
                                            ? 'rounded-full bg-brand-accent-100 p-2 transition-all'
                                            : 'px-2',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'flex h-12 w-12 items-center justify-center rounded-full transition-all',
                                            isActive &&
                                                'border-2 border-brand-accent bg-card text-brand-accent shadow-lg',
                                            isCompleted &&
                                                'bg-brand-accent text-on-accent',
                                            !isActive &&
                                                !isCompleted &&
                                                'border-2 border-border-strong bg-card text-muted-foreground',
                                        )}
                                    >
                                        {isCompleted ? (
                                            <CheckCircle2 className="h-7 w-7" />
                                        ) : (
                                            <Icon className="h-7 w-7" />
                                        )}
                                    </div>
                                </div>

                                {index < SETUP_STEPS.length - 1 && (
                                    <div className="w-0.5 flex-1">
                                        <div
                                            className={cn(
                                                'h-full w-full transition-all',
                                                isCompleted
                                                    ? 'bg-brand-accent'
                                                    : 'border-l-2 border-dashed border-input bg-border-strong',
                                            )}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex-1 pb-16">
                                <h3 className="mb-3 text-xl font-bold">
                                    <SetupStepTitle
                                        stepNumber={number}
                                        current={current}
                                        progress={progress}
                                    />
                                </h3>
                                <p className="text-muted-foreground">
                                    {step.description}
                                </p>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * The left card of the setup funnel, matching the register page's welcome
 * card: a gradient surface with a badge, a welcome heading, two lines of
 * context and the step list. Desktop only; phones get the compact stepper
 * above the form instead.
 */
export function SetupSidePanel({
    intro,
    current,
    progress,
}: {
    intro: [string, string];
    current: 1 | 2 | 3 | 4 | 5;
    progress: SetupProgress;
}) {
    return (
        <div className="hidden h-fit self-start rounded-xl border bg-card bg-linear-to-t from-brand-accent-100 to-brand-primary-100 px-8 py-6 shadow-xl lg:sticky lg:top-8 lg:col-span-3 lg:block">
            <div className="mb-8 flex items-center gap-4 pt-6">
                <div className="mb-4 flex justify-center">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-linear-to-r from-brand-primary to-brand-primary-600 shadow-lg">
                        <BadgeCheck
                            className="h-8 w-8 text-white"
                            aria-hidden
                        />
                    </div>
                </div>
                <div>
                    <h2 className="mb-0 text-xl font-bold">Welcome, aboard!</h2>
                    <p className="text-muted-foreground">{intro[0]}</p>
                    <p className="text-muted-foreground">{intro[1]}</p>
                </div>
            </div>

            <div>
                <SetupLeftIndicator current={current} progress={progress} />
            </div>
        </div>
    );
}

/**
 * The same steps as a compact card above the form, shown only where the side
 * panel is hidden.
 */
export function SetupStepsTopCard({
    current,
    progress,
}: {
    current: 1 | 2 | 3 | 4 | 5;
    progress: SetupProgress;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 lg:hidden">
            <SetupSteps current={current} progress={progress} />
        </div>
    );
}

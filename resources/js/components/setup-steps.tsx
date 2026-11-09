import { Link } from '@inertiajs/react';
import { BadgeDollarSign, Building2, Check, House, Rocket, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { show as becomeLandlord } from '@/routes/guest/become-landlord';
import { firstAsset, plan as planStep } from '@/routes/onboarding';
import { review, team } from '@/routes/tenant/setup';

export type SetupStep = {
    title: string;
    description: string;
    icon: LucideIcon;
    /**
     * Whether the funnel tolerates passing this step without completing it.
     * Only the team step is skippable — organization and first asset gate
     * everything downstream, and review is the terminal step.
     */
    skippable: boolean;
};

/**
 * Completion state for the funnel, reported by every setup page. Titles
 * link between steps, but a step is only clickable when no unskippable
 * step before it is still incomplete — so from "Set up organization" with
 * no organization yet, "Invite your team" stays plain text instead of
 * leapfrogging the mandatory steps.
 */
export type SetupProgress = {
    hasOrganization: boolean;
    hasAsset: boolean;
    hasConfirmedPlan: boolean;
};

/**
 * The landlord funnel, in order. One list feeds both the desktop side panel
 * and the mobile stepper so the two can never disagree about what comes next.
 */
export const SETUP_STEPS: SetupStep[] = [
    {
        title: 'Set up organization',
        description: 'Name and currency',
        icon: Building2,
        skippable: false,
    },
    {
        title: 'Add your first asset',
        description: 'A building or a land parcel',
        icon: House,
        skippable: false,
    },
    {
        title: 'Pick your plan',
        description: 'First month free',
        icon: BadgeDollarSign,
        skippable: false,
    },
    {
        title: 'Invite your team',
        description: 'Optional — skip anytime',
        icon: Users,
        skippable: true,
    },
    {
        title: 'Review and launch',
        description: 'Confirm and open your dashboard',
        icon: Rocket,
        skippable: false,
    },
];

function isStepComplete(
    stepNumber: number,
    progress: SetupProgress,
): boolean {
    switch (stepNumber) {
        case 1:
            return progress.hasOrganization;
        case 2:
            return progress.hasAsset;
        case 3:
            return progress.hasConfirmedPlan;
        default:
            return true;
    }
}

/**
 * A step is reachable when every unskippable step before it is complete.
 * The current step is never a link; going back is always allowed because
 * its predecessors were completed to get here.
 */
export function canReachStep(
    stepNumber: number,
    progress: SetupProgress,
): boolean {
    return SETUP_STEPS.slice(0, stepNumber - 1).every(
        (step, index) =>
            step.skippable || isStepComplete(index + 1, progress),
    );
}

/**
 * The first incomplete unskippable step before the target, for the
 * disabled tooltip. Null when the target is reachable.
 */
export function blockingStep(
    stepNumber: number,
    progress: SetupProgress,
): SetupStep | null {
    const blocked = SETUP_STEPS.slice(0, stepNumber - 1).find(
        (step, index) =>
            !step.skippable && !isStepComplete(index + 1, progress),
    );

    return blocked ?? null;
}

function setupStepHref(stepNumber: number) {
    switch (stepNumber) {
        case 1:
            return becomeLandlord();
        case 2:
            return firstAsset();
        case 3:
            return planStep();
        case 4:
            return team();
        default:
            return review();
    }
}

/**
 * A step title: a link when the step is reachable and not current, plain
 * text otherwise. Unreachable future steps expose why via the title
 * attribute and `aria-disabled`.
 */
export function SetupStepTitle({
    stepNumber,
    current,
    progress,
    className,
}: {
    stepNumber: number;
    current: 1 | 2 | 3 | 4 | 5;
    progress: SetupProgress;
    className?: string;
}) {
    const title = SETUP_STEPS[stepNumber - 1].title;

    if (stepNumber === current) {
        return <span className={className}>{title}</span>;
    }

    if (!canReachStep(stepNumber, progress)) {
        const blocker = blockingStep(stepNumber, progress);

        return (
            <span
                className={cn(className, 'cursor-not-allowed')}
                aria-disabled="true"
                title={
                    blocker
                        ? `Complete “${blocker.title}” first`
                        : 'Not available yet'
                }
            >
                {title}
            </span>
        );
    }

    return (
        <Link
            href={setupStepHref(stepNumber)}
            className={cn(className, 'transition-colors hover:text-foreground')}
        >
            {title}
        </Link>
    );
}

/**
 * Compact horizontal stepper for phones, where the side panel is hidden.
 * Text labels, not just dots: someone arriving mid-flow should read what is
 * next rather than decode a position.
 */
export function SetupSteps({
    current,
    progress,
}: {
    current: 1 | 2 | 3 | 4 | 5;
    progress: SetupProgress;
}) {
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
                        <SetupStepTitle
                            stepNumber={number}
                            current={current}
                            progress={progress}
                            className={cn(
                                'truncate text-sm',
                                active
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        />
                    </li>
                );
            })}
        </ol>
    );
}

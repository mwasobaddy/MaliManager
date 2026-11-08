import { SetupSteps } from '@/components/setup-steps';

/**
 * The funnel's welcome panel, adapted from the DA register inspo: a brand
 * gradient card carrying the welcome copy and the step indicator, stacked
 * above the content instead of beside it — the side-by-side version would
 * squeeze form grids exactly the way the auth split-card did.
 */
export function SetupWelcome({
    title,
    description,
    current,
}: {
    title: string;
    description: string;
    current: 1 | 2;
}) {
    return (
        <div className="rounded-xl border bg-gradient-to-br from-brand-primary-100 via-card to-brand-accent-100 p-6">
            <p className="text-xl font-semibold tracking-tight text-foreground">
                {title}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
            <div className="mt-4">
                <SetupSteps current={current} />
            </div>
        </div>
    );
}

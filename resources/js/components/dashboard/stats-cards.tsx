import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

type StatItem = {
    /** Full label including qualifier, e.g. "Total Organisations" not just "Organisations" */
    label: string;
    value: number | string;
    /** Optional brand color variant for the stat */
    variant?: 'primary' | 'accent';
};

type StatsCardsProps = {
    label: string;
    summary?: string;
    items: StatItem[];
};

/**
 * Renders stats as compact inline pills with brand color variants.
 * Layout: Label: value  —  horizontal, compact, no icons.
 * Each stat can carry a variant for a subtle brand-color background.
 */
export function StatsCards({ label, summary, items }: StatsCardsProps) {
    return (
        <Collapsible className="group/collapsible" defaultOpen>
            <CollapsibleTrigger asChild>
                <button className="flex w-full items-center gap-2 rounded-lg p-2 text-sm font-medium text-muted-foreground hover:bg-muted/50 hover:text-foreground">
                    {label}
                    {summary && (
                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                            {summary}
                        </span>
                    )}
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-3 flex flex-wrap gap-2">
                    {items.map((item) => (
                        <StatPill key={item.label} item={item} />
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

function StatPill({ item }: { item: StatItem }) {
    const baseStyles =
        'inline-flex items-center gap-2 rounded-lg border border-border-strong px-3 py-2 text-sm font-medium transition-colors hover:border-brand-primary/30';

    // Quiet tints, never full fills: a row of solid pills competes like
    // CTAs (§5 60-30-10). Colour lives in the value figure; the label stays
    // muted so normal states stay quiet and real exceptions can shout.
    const variantStyles = {
        primary:
            'bg-brand-primary-100 text-primary-text border-brand-primary/25',
        accent: 'bg-brand-accent-100 text-accent-on-tint border-brand-accent/25',
    };

    const variantClass = variantStyles[item.variant ?? 'primary'];

    return (
        <span className={`${baseStyles} ${variantClass}`}>
            <span className="font-normal opacity-90">{item.label}</span>
            <span className="font-bold tabular-nums">{item.value}</span>
        </span>
    );
}

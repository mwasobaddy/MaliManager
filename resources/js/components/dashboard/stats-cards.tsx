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
    variant?: 'primary' | 'accent' | 'success' | 'warning' | 'info' | 'default';
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

    const variantStyles = {
        primary: 'bg-brand-primary/10 text-brand-primary-600 border-brand-primary/20 dark:bg-brand-primary-100/20 dark:text-brand-primary dark:border-brand-primary/30',
        accent: 'bg-brand-accent-100/50 text-brand-accent border-brand-accent/20 dark:bg-brand-accent-100/20 dark:text-brand-accent',
        success: 'bg-status-success/10 text-status-success border-status-success/20 dark:bg-status-success/20 dark:text-status-success',
        warning: 'bg-status-warning/10 text-status-warning border-status-warning/20 dark:bg-status-warning/20 dark:text-status-warning',
        info: 'bg-status-info/10 text-status-info border-status-info/20 dark:bg-status-info/20 dark:text-status-info',
        default: 'bg-card text-foreground border-border-strong',
    };

    const variantClass = variantStyles[item.variant ?? 'default'] || variantStyles.default;

    return (
        <span className={`${baseStyles} ${variantClass}`}>
            <span className="font-medium text-foreground">
                {item.label}
            </span>
            <span className="font-semibold tabular-nums text-foreground">
                {item.value}
            </span>
        </span>
    );
}

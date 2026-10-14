import { ChevronDown, ChevronUp } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

type StatItem = {
    title: string;
    value: number | string;
    description?: string;
    icon: ReactNode;
    /** Optional brand color variant for the stat */
    variant?: 'primary' | 'accent' | 'success' | 'warning' | 'info' | 'default';
};

type StatsCardsProps = {
    label: string;
    summary?: string;
    items: StatItem[];
};

/**
 * Renders stats as branded "pill" cards with colored backgrounds.
 * Each stat can have a variant that applies a brand color background.
 * Retains the collapsible/expandable behavior of the original StatsCards.
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
                    <ChevronDown className="h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:hidden" />
                    <ChevronUp className="h-4 w-4 transition-transform duration-200 group-data-[state=closed]/collapsible:hidden" />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {items.map((item) => (
                        <StatPill key={item.title} item={item} />
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

function StatPill({ item }: { item: StatItem }) {
    const variant = item.variant ?? 'default';

    const baseStyles =
        'flex flex-col gap-1.5 rounded-xl border border-border-strong p-4 transition-colors hover:border-brand-primary/30';

    const variantStyles = {
        primary: 'bg-brand-primary/10 text-brand-primary-600 border-brand-primary/20 dark:bg-brand-primary-100/20 dark:text-brand-primary dark:border-brand-primary/30',
        accent: 'bg-brand-accent-100/50 text-brand-accent border-brand-accent/20 dark:bg-brand-accent-100/20 dark:text-brand-accent',
        success: 'bg-status-success/10 text-status-success border-status-success/20 dark:bg-status-success/20 dark:text-status-success',
        warning: 'bg-status-warning/10 text-status-warning border-status-warning/20 dark:bg-status-warning/20 dark:text-status-warning',
        info: 'bg-status-info/10 text-status-info border-status-info/20 dark:bg-status-info/20 dark:text-status-info',
        default: 'bg-card text-foreground border-border-strong',
    };

    const variantClass = variantStyles[variant] || variantStyles.default;

    return (
        <div className={`${baseStyles} ${variantClass}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-medium text-muted-foreground truncate">
                        {item.title}
                    </p>
                    <p className="text-xl font-bold lg:text-2xl tabular-nums">
                        {item.value}
                    </p>
                    {item.description && (
                        <p className="text-xs text-muted-foreground mt-1 line-clamp-1">
                            {item.description}
                        </p>
                    )}
                </div>
                <div className="flex-shrink-0 rounded-full bg-muted/50 p-2 [&_svg]:h-5 [&_svg]:w-5 text-muted-foreground/80">
                    {item.icon}
                </div>
            </div>
        </div>
    );
}

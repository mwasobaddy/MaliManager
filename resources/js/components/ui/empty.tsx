import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyProps = {
    /** Glyph for the circle. Decorative — the title carries the meaning. */
    icon?: ReactNode;
    title: string;
    description?: string;
    /** Primary CTA, usually a terracotta Button. */
    action?: ReactNode;
    /** Secondary link, usually `--accent-text`. */
    secondaryAction?: ReactNode;
    /**
     * Tighter vertical rhythm for empty states inside a table body, where the
     * surrounding card already provides breathing room.
     */
    compact?: boolean;
    className?: string;
};

/**
 * Empty state, per v2 §6.6: icon in a `--brand-primary-100` circle, title in
 * `--text`, description in `--text-muted`, primary CTA terracotta and a
 * secondary link in accent.
 *
 * `data-table` previously fell back to the bare string "Nothing to show.",
 * which says nothing about what is missing or what to do about it. Every
 * placeholder now has somewhere to put a next step.
 */
export function Empty({
    icon,
    title,
    description,
    action,
    secondaryAction,
    compact = false,
    className,
}: EmptyProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center text-center',
                compact ? 'gap-2 px-4 py-8' : 'gap-3 px-6 py-12',
                className,
            )}
        >
            {icon && (
                <span
                    className={cn(
                        'bg-brand-primary-100 text-primary-text grid place-items-center rounded-full',
                        compact ? 'size-9' : 'size-12',
                    )}
                    aria-hidden
                >
                    {icon}
                </span>
            )}

            <p className="text-foreground text-sm font-semibold">{title}</p>

            {description && (
                <p className="text-muted-foreground max-w-sm text-sm">
                    {description}
                </p>
            )}

            {(action || secondaryAction) && (
                <div className="mt-1 flex flex-wrap items-center justify-center gap-3">
                    {action}
                    {secondaryAction}
                </div>
            )}
        </div>
    );
}

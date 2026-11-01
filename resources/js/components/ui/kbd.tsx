import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * Keyboard hint, per v2 §6.9: `--bg-base` on a `--border` edge with
 * `--text-muted` text, in mono at 12px.
 *
 * Mono is right here and nowhere near money — this is a literal key legend, the
 * one place §4.1 allows monospace outside of IDs and codes.
 */
export function Kbd({ className, ...props }: ComponentProps<'kbd'>) {
    return (
        <kbd
            data-slot="kbd"
            className={cn(
                'bg-bg-base border-border text-muted-foreground inline-flex h-5 min-w-5 items-center justify-center rounded border px-1 font-mono text-[11px] font-medium',
                className,
            )}
            {...props}
        />
    );
}

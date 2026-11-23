import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * The colour lives on the ICON chip only; the button itself stays transparent.
 * A filled pill behind an icon-plus-label pair reads as a second, competing
 * button in a header that already has other controls.
 */
const iconChipVariants = cva(
    'inline-flex size-9 items-center justify-center rounded-lg transition-colors coarse:size-11',
    {
        variants: {
            color: {
                primary: 'bg-primary text-primary-foreground',
                brand: 'bg-brand-primary text-white',
                teal: 'bg-brand-accent text-on-accent',
                view: 'bg-action-view text-white',
                edit: 'bg-action-edit text-white',
                danger: 'bg-action-danger text-white',
                default: 'bg-muted text-muted-foreground',
            },
        },
        defaultVariants: {
            color: 'primary',
        },
    },
);

const labelToneVariants = cva('text-xs leading-tight font-medium', {
    variants: {
        color: {
            primary: 'text-foreground',
            brand: 'text-foreground',
            teal: 'text-foreground',
            view: 'text-foreground',
            edit: 'text-foreground',
            danger: 'text-destructive',
            default: 'text-muted-foreground',
        },
    },
    defaultVariants: {
        color: 'primary',
    },
});

type LabelledIconButtonProps = {
    /** The icon glyph. Rendered inside a coloured chip. */
    icon: ReactNode;
    /** Short visible label rendered under the icon. */
    label: string;
    /**
     * Full text for the hover tooltip. Defaults to `label`, so only set it when
     * the short label is an abbreviation ("Create" -> "Create organisation").
     */
    tooltip?: string;
    /**
     * When set, the button disables and the tooltip carries this reason
     * instead. Disabled buttons do not fire hover, so the trigger wraps in
     * a span to keep the explanation reachable by mouse, keyboard and touch.
     */
    disabledReason?: string | null;
    color?:
        'primary' | 'brand' | 'teal' | 'view' | 'edit' | 'danger' | 'default';
    /** Inertia target for navigation; omit for a <button>. */
    href?: InertiaLinkProps['href'];
    onClick?: () => void;
    type?: 'button' | 'submit';
    disabled?: boolean;
    className?: string;
    /** QA hook, e.g. `data-test="new-payment-button"`. */
    'data-test'?: string;
};

/**
 * A compact icon-chip-over-label control.
 *
 * The button surface is transparent — only the icon carries the colour — so a
 * header can hold a labelled primary action without it competing with the
 * icon-only utility buttons beside it.
 */
export function LabelledIconButton({
    icon,
    label,
    tooltip,
    disabledReason,
    color = 'primary',
    href,
    onClick,
    type = 'button',
    disabled = false,
    className,
    'data-test': dataTest,
}: LabelledIconButtonProps) {
    const isDisabled = disabled || disabledReason != null;
    const tooltipText =
        isDisabled && disabledReason ? disabledReason : (tooltip ?? label);
    const content = (
        <>
            <span
                className={cn(
                    iconChipVariants({ color }),
                    !isDisabled && 'group-hover:brightness-95',
                )}
            >
                <span className="flex items-center justify-center [&_svg]:size-5">
                    {icon}
                </span>
            </span>
            <span
                className={cn(
                    'text-center max-sm:hidden sm:line-clamp-2',
                    labelToneVariants({ color }),
                )}
            >
                {label}
            </span>
        </>
    );

    const classes = cn(
        'group inline-flex w-full max-w-24 flex-col items-center justify-center gap-1.5 rounded-lg px-1 py-1.5 transition-colors focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 max-sm:w-auto',
        className,
    );

    const trigger =
        href && !isDisabled ? (
            <Link
                href={href}
                className={classes}
                aria-label={tooltip ?? label}
                data-test={dataTest}
            >
                {content}
            </Link>
        ) : (
            <button
                type={type}
                onClick={onClick}
                disabled={isDisabled}
                className={classes}
                aria-label={tooltip ?? label}
                data-test={dataTest}
            >
                {content}
            </button>
        );

    const triggerWithHover = isDisabled ? (
        <span className="inline-flex">{trigger}</span>
    ) : (
        trigger
    );

    return (
        <Tooltip>
            <TooltipTrigger asChild>{triggerWithHover}</TooltipTrigger>
            <TooltipContent side="bottom">{tooltipText}</TooltipContent>
        </Tooltip>
    );
}

import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Colors for table row actions.
 *
 * Row actions are **solid at rest, brightness-shifted on hover**: view is
 * blue, edit is green, delete is red, so the action reads before the
 * tooltip does. Hover darkens in light mode and lightens in dark mode
 * (`dark:` brightness), keeping white icons at AA on both canvases — the
 * fills are fixed tokens for exactly that reason, not the theme-flipping
 * tints. (v2 §7.3 ran these neutral-at-rest; reversed by owner request —
 * the wall-of-badges concern stands, so keep these three to row actions
 * only and never promote them to page-level CTAs.)
 *
 * Each maps to a token in resources/css/app.css.
 */
const actionColorVariants = cva('', {
    variants: {
        color: {
            default: 'bg-muted text-muted-foreground hover:bg-accent',
            primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
            view: 'bg-action-view-fill text-white hover:brightness-90 focus-visible:ring-action-view-fill/40 dark:hover:brightness-125',
            edit: 'bg-action-edit-fill text-white hover:brightness-90 focus-visible:ring-action-edit-fill/40 dark:hover:brightness-125',
            danger: 'bg-action-danger-fill text-white hover:brightness-90 focus-visible:ring-action-danger-fill/40 dark:hover:brightness-125',
            brand: 'bg-muted text-muted-foreground hover:bg-brand-primary-100 hover:text-primary-text focus-visible:ring-primary-text/30',
            accent: 'bg-brand-accent/12 text-brand-accent hover:bg-brand-accent/20 focus-visible:ring-brand-accent/30',
            /**
             * Solid teal for a table's utility controls (filter, export,
             * import). Watch the §5 trap here: putting all three on solid teal
             * at once breaks "never more than one solid teal element per
             * view", because three in a row stop being an accent and become
             * the interface. Prefer `variant="accent"` on the Button when the
             * page already shows a solid teal somewhere else.
             */
            teal: 'bg-brand-accent text-on-accent hover:bg-brand-accent/90 focus-visible:ring-brand-accent/40',
        },
    },
    defaultVariants: {
        color: 'default',
    },
});

type ActionIconButtonProps = React.ComponentProps<typeof Button> & {
    /**
     * The icon to render. Leave off the size class — the button sizes it.
     * Omit when using `asChild`, where the child element supplies the content.
     */
    icon?: ReactNode;
    /**
     * Tooltip text. Also used as the accessible name, so icon-only actions stay
     * announced correctly by screen readers.
     */
    label: string;
    /**
     * Semantic color. `view` blue, `edit` green, `danger` red, `brand`
     * terracotta, `accent` teal, or a shadcn default.
     */
    color?:
        | 'default'
        | 'primary'
        | 'view'
        | 'edit'
        | 'danger'
        | 'brand'
        | 'accent'
        | 'teal';
    /** Which side the tooltip opens on. */
    tooltipSide?: 'top' | 'right' | 'bottom' | 'left';
    /**
     * When set, the button disables and the tooltip carries this reason
     * instead. Disabled buttons do not fire hover, so the trigger wraps
     * in a span to keep the explanation reachable by mouse and keyboard.
     */
    disabledReason?: string | null;
};

/**
 * Icon-only action button for table rows, with a styled tooltip on hover.
 *
 * Wraps the shadcn `Button` (size `icon`) in a Radix `Tooltip`, so it replaces
 * the bare `title` attribute that table actions used previously — the native
 * tooltip was unstyled and slow to appear.
 *
 * Three call shapes:
 *
 * Plain action:
 *   <ActionIconButton icon={<Eye />} label="View organization" color="view"
 *       onClick={() => openView(org.id)} />
 *
 * Navigation — pass `asChild` and the child, not `icon`:
 *   <ActionIconButton asChild label="Edit organization" color="edit">
 *       <Link href={routes.edit(org.id)}>
 *           <Pencil />
 *       </Link>
 *   </ActionIconButton>
 *
 * Destructive submit — wrap in Inertia's `Form` (which renders a real <form>)
 * rather than `as="button"`, so the button is not nested inside another button:
 *   <Form method="post" action={routes.destroy(org.id)} className="inline">
 *       <ActionIconButton type="submit" icon={<Trash2 />} color="danger"
 *           label={`Delete ${org.name}`} />
 *   </Form>
 */
export function ActionIconButton({
    icon,
    label,
    color = 'default',
    tooltipSide = 'top',
    disabledReason,
    className,
    type = 'button',
    asChild = false,
    children,
    disabled,
    ...props
}: ActionIconButtonProps) {
    const isDisabled = disabled || disabledReason != null;

    const trigger = (
        <Button
            asChild={asChild}
            type={type}
            variant="ghost"
            size="icon"
            aria-label={label}
            disabled={isDisabled}
            className={cn(
                // Icon-only row actions meet the 44px touch target on
                // coarse pointers; fine pointers keep the compact 36px.
                'coarse:min-h-11 coarse:min-w-11',
                actionColorVariants({ color }),
                className,
            )}
            {...props}
        >
            {/*
             * With asChild the caller's element (e.g. an Inertia <Link>)
             * is the real trigger and carries the content; otherwise the
             * icon we were handed is. Passing `icon` unconditionally
             * would hand Slot an undefined child and render nothing.
             */}
            {asChild ? children : icon}
        </Button>
    );

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {/*
                 * Disabled buttons swallow hover, so the reason would be
                 * unreachable without the wrapper.
                 */}
                {isDisabled ? (
                    <span className="inline-flex">{trigger}</span>
                ) : (
                    trigger
                )}
            </TooltipTrigger>
            <TooltipContent side={tooltipSide}>
                {isDisabled && disabledReason ? disabledReason : label}
            </TooltipContent>
        </Tooltip>
    );
}

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
 * v2 §7.3 is **neutral at rest, tinted on hover**. v1 gave every row a filled
 * View/Edit/Delete chip, so the same three colours repeated down every column
 * and the table read as a wall of badges before you got to the data. The icons
 * are identical on every row, so colour was never carrying unique information
 * at rest — it was decoration. Hovering is where it earns its place: it says
 * what the button does *before* you commit to it, which is the moment the
 * distinction is actually useful.
 *
 * Each maps to a token that has its own light and dark values in
 * resources/css/app.css, so a swatch re-tints itself with the theme and no
 * `dark:` variants are needed here.
 */
const actionColorVariants = cva('', {
    variants: {
        color: {
            default: 'bg-muted text-muted-foreground hover:bg-accent',
            primary:
                'bg-primary text-primary-foreground hover:bg-primary/90',
            /* v2 §7.3 — neutral at rest, tinted on hover. See the block
               comment above for why the v1 fills went away. */
            view: 'bg-muted text-muted-foreground hover:bg-brand-accent-100 hover:text-accent-text focus-visible:ring-accent-text/30',
            edit: 'bg-muted text-muted-foreground hover:bg-brand-primary-100 hover:text-primary-text focus-visible:ring-primary-text/30',
            danger:
                'bg-muted text-muted-foreground hover:bg-status-danger-bg hover:text-status-danger focus-visible:ring-status-danger/30',
            brand: 'bg-muted text-muted-foreground hover:bg-brand-primary-100 hover:text-primary-text focus-visible:ring-primary-text/30',
            accent:
                'bg-brand-accent/12 text-brand-accent hover:bg-brand-accent/20 focus-visible:ring-brand-accent/30',
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
    color?: 'default' | 'primary' | 'view' | 'edit' | 'danger' | 'brand' | 'accent' | 'teal';
    /** Which side the tooltip opens on. */
    tooltipSide?: 'top' | 'right' | 'bottom' | 'left';
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
    className,
    type = 'button',
    asChild = false,
    children,
    ...props
}: ActionIconButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    asChild={asChild}
                    type={type}
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    className={cn(actionColorVariants({ color }), className)}
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
            </TooltipTrigger>
            <TooltipContent side={tooltipSide}>{label}</TooltipContent>
        </Tooltip>
    );
}
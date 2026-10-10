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
 * Colors for table row actions. Each maps to a design token that already has
 * separate light/dark values (`--action-view`, `--action-edit`,
 * `--action-danger`, `--brand-primary`, `--brand-accent` in resources/css/app.css),
 * so the swatch re-tints itself in dark mode without `dark:` variants here.
 *
 * Backgrounds are deliberately translucent (`/12`) with a saturated icon and
 * matching border. A row of solid fills is too loud at table density; the tint
 * keeps the action identifiable while staying scannable.
 */
const actionColorVariants = cva('', {
    variants: {
        color: {
            default: 'bg-muted text-muted-foreground hover:bg-accent',
            primary:
                'bg-primary text-primary-foreground hover:bg-primary/90',
            view: 'bg-action-view/12 text-action-view hover:bg-action-view/20 focus-visible:ring-action-view/30',
            edit: 'bg-action-edit/12 text-action-edit hover:bg-action-edit/20 focus-visible:ring-action-edit/30',
            danger:
                'bg-action-danger/12 text-action-danger hover:bg-action-danger/20 focus-visible:ring-action-danger/30',
            brand: 'bg-brand-primary/12 text-brand-primary hover:bg-brand-primary/20 focus-visible:ring-brand-primary/30',
            accent:
                'bg-brand-accent/12 text-brand-accent hover:bg-brand-accent/20 focus-visible:ring-brand-accent/30',
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
    color?: 'default' | 'primary' | 'view' | 'edit' | 'danger' | 'brand' | 'accent';
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
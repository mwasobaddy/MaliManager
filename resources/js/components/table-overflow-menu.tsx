import type { ReactNode } from 'react';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';

export type TableOverflowAction = {
    icon: ReactNode;
    label: string;
    onSelect: () => void;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    actions: TableOverflowAction[];
    title?: string;
    description?: string;
};

/**
 * Mobile overflow for table toolbars. Below `sm` the Filters/Import/Export
 * icon row collapses into a MoreHorizontal trigger; this lists the same
 * actions with icons and full names in a bottom drawer (ResponsiveOverlay
 * draws from the bottom on small screens, where a dropdown would miss the
 * thumb zone and clip against the table edge).
 *
 * Choosing an action closes the menu first and then opens that action's
 * sheet — overlays never stack, or Radix fights over the focus trap. The
 * primary Add button stays out of this menu on its own row; the overflow
 * protects its prominence instead of hiding it.
 */
export function TableOverflowMenu({
    open,
    onOpenChange,
    actions,
    title = 'More actions',
    description = 'Choose what to do with this list.',
}: Props) {
    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
        >
            <div className="grid gap-1 py-2">
                {actions.map((action) => (
                    <Button
                        key={action.label}
                        type="button"
                        variant="ghost"
                        className="h-11 w-full justify-start gap-3 px-3 text-sm font-normal"
                        onClick={() => {
                            onOpenChange(false);
                            action.onSelect();
                        }}
                    >
                        <span className="flex size-4 shrink-0 items-center justify-center [&_svg]:size-4">
                            {action.icon}
                        </span>
                        {action.label}
                    </Button>
                ))}
            </div>
        </ResponsiveOverlay>
    );
}

import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type AddButtonProps = React.ComponentProps<typeof Button> & {
    /** Visible label, e.g. "Add unit". */
    label: string;
    /**
     * Hover text. Defaults to the label; pass a fuller description when
     * the label alone does not say where the record goes.
     */
    tooltip?: string;
    /**
     * When set, the button disables and the tooltip carries this reason
     * instead (plan limits, missing prerequisites). Disabled buttons do
     * not fire hover, so the trigger wraps in a span to keep the
     * explanation reachable by mouse, keyboard and touch.
     */
    disabledReason?: string | null;
    /** Which side the tooltip opens on. Bottom by default: header buttons
     * sit at the top of the viewport where a top tooltip would clip. */
    tooltipSide?: 'top' | 'right' | 'bottom' | 'left';
    children?: ReactNode;
};

/**
 * The one header create button, shared by every index page: solid fill,
 * Plus icon, label text, and a tooltip that names the action — or the
 * reason it is currently unavailable. Matches ActionIconButton's Radix
 * tooltip (not the native `title`), inside the same global provider.
 */
export function AddButton({
    label,
    tooltip,
    disabledReason,
    tooltipSide = 'bottom',
    className,
    type = 'button',
    asChild = false,
    children,
    disabled,
    ...props
}: AddButtonProps) {
    const isDisabled = disabled || disabledReason != null;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex">
                    <Button
                        asChild={asChild}
                        type={type}
                        disabled={isDisabled}
                        className={cn(className)}
                        {...props}
                    >
                        {asChild ? (
                            children
                        ) : (
                            <>
                                <Plus className="size-4" />
                                {label}
                            </>
                        )}
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent side={tooltipSide}>
                {isDisabled && disabledReason
                    ? disabledReason
                    : (tooltip ?? label)}
            </TooltipContent>
        </Tooltip>
    );
}

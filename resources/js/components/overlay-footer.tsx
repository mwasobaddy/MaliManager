import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { DialogClose } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Semantic role of an action button. The mapping to a shadcn variant lives
 * here and nowhere else, so the same word always means the same colour.
 *
 * - `primary` — the one action the dialog exists to perform.
 * - `secondary` — a real but optional alternative, still neutral.
 * - `danger` — the destructive confirmation itself.
 * - `danger-ghost` — destructive *intent* without the confirmation weight,
 *   for a button that only navigates to the confirmation. Keeps `--action-danger`
 *   as the colour while avoiding a second filled pill in the row.
 */
export type OverlayActionTone =
    | 'primary'
    | 'secondary'
    | 'danger'
    | 'danger-ghost';

const TONE_VARIANT = {
    primary: 'default',
    secondary: 'outline',
    danger: 'destructive',
    'danger-ghost': 'ghost',
} as const;

/** Extra classes the variant alone cannot express. */
const TONE_CLASS = {
    primary: '',
    secondary: '',
    danger: '',
    'danger-ghost':
        'text-action-danger hover:bg-action-danger/10 hover:text-action-danger',
} as const;

export type OverlayAction = {
    label: string;
    /** Defaults to `primary`. */
    tone?: OverlayActionTone;
    /** Triggers an in-page handler (close the overlay, run an export). */
    onClick?: () => void;
    /** Submits the form with this id. Used for buttons that live in the body. */
    form?: string;
    type?: 'button' | 'submit';
    disabled?: boolean;
    icon?: ReactNode;
    /** Emitted as `data-test`, for browser/Playwright selectors. */
    testId?: string;
};

type OverlayFooterProps = {
    /** Renders the Cancel button. Omit it for acknowledgements. */
    onCancel?: () => void;
    /**
     * Renders Cancel as a Radix `DialogClose` instead of calling a handler.
     * Use this for uncontrolled dialogs that have no `open` state to close;
     * the button then also fires any surrounding close behaviour.
     */
    cancelClosesDialog?: boolean;
    cancelLabel?: string;
    actions?: OverlayAction[];
    className?: string;
};

/**
 * The action row for a Dialog or Sheet.
 *
 * **Layout.** Right-aligned, wrapping, with Cancel first and the primary action
 * last. That order is not cosmetic: the eye reads left to right, the primary
 * action is the destination, and keyboard focus moves *forward* toward it —
 * which is also why it sits on the right. The row wraps rather than stacking,
 * because a stacked `flex-col` makes each button stretch to the full panel
 * width, and a wall of full-width buttons reads as a list, not an action.
 *
 * **Colour.** Cancel is always `shadcndefault` — the neutral fill, the same
 * chrome as the overlay's close button, so the two dismiss controls match.
 * It is deliberately *not* `ghost` (too little presence beside a filled
 * primary) and never `outline` (beside a destructive primary, an outlined
 * Cancel gives two competing pills and the destructive verb stops owning the
 * row). One rule for every dialog beats a rule that shifts with the primary's
 * tone.
 *
 * Tone is decided by *meaning*, never by position — a second `secondary` action
 * sits beside the primary without demoting the primary.
 */
export function OverlayFooter({
    onCancel,
    cancelClosesDialog = false,
    cancelLabel = 'Cancel',
    actions = [],
    className,
}: OverlayFooterProps) {
    if (!onCancel && !cancelClosesDialog && actions.length === 0) {
        return null;
    }

    const cancel = (
        <Button type="button" variant="shadcndefault" onClick={onCancel}>
            {cancelLabel}
        </Button>
    );

    return (
        <div
            className={cn(
                'flex w-full flex-wrap items-center justify-end gap-2',
                className,
            )}
        >
            {cancelClosesDialog ? (
                <DialogClose asChild>{cancel}</DialogClose>
            ) : (
                onCancel && cancel
            )}

            {actions.map((action) => {
                const tone = action.tone ?? 'primary';

                return (
                    <Button
                        key={action.label}
                        type={action.type ?? 'button'}
                        variant={TONE_VARIANT[tone]}
                        className={TONE_CLASS[tone]}
                        form={action.form}
                        disabled={action.disabled}
                        onClick={action.onClick}
                        data-test={action.testId}
                    >
                        {action.icon}
                        {action.label}
                    </Button>
                );
            })}
        </div>
    );
}
import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type FormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    /** Extra classes for the dialog surface. */
    className?: string;
};

/**
 * A centred Modal for a short form — 3–5 fields, one screen, no repeated groups.
 *
 * **Why a modal and not a sheet.** A short form is a small digression, and a
 * centred dialog is the shape users already read as "answer this and get back
 * to what you were doing". The list behind it stays visible around the edges,
 * which is enough context for three fields; the sheet is for the longer edit
 * form where the user needs the table readable beside the fields.
 *
 * **When to use the sheet instead.** Editing (see `ResponsiveOverlay`), and any
 * create that grows a repeated group, a step sequence, or validation that only
 * reveals itself section by section — those need room a modal does not have.
 * Escalate to a full page rather than letting a modal scroll.
 *
 * `DialogContent` renders its own close button, so this component adds none.
 */
export function FormModal({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    className,
}: FormModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={className}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {/* The soft keyboard removes roughly 300px of viewport on a
                    phone, so the body scrolls inside a capped height rather
                    than pushing the footer off-screen. */}
                <div className="max-h-[60vh] overflow-y-auto">{children}</div>

                {footer && <DialogFooter>{footer}</DialogFooter>}
            </DialogContent>
        </Dialog>
    );
}

import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

type ResponsiveOverlayProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    /** Max width of the centred dialog on small screens. */
    className?: string;
};

/**
 * A single overlay that renders as a right-hand Sheet on desktop and a
 * centred Dialog on small screens.
 *
 * The repo already ships both primitives (Radix Dialog + Sheet with slide
 * animation), so this only picks between them — no new overlay primitive is
 * needed. The switch has to happen in JS: CSS cannot swap two different Radix
 * roots, and mounting both would trap focus twice.
 *
 * `SheetContent` already supplies the slide + overlay classes; the Dialog
 * branch adds the centre/max-width classes the Sheet lacks.
 */
export function ResponsiveOverlay({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    className,
}: ResponsiveOverlayProps) {
    const isMobile = useIsMobile();

    if (isMobile) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className={className}>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>
                                {description}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {children}
                    </div>
                    {footer && <DialogFooter>{footer}</DialogFooter>}
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    {description && (
                        <SheetDescription>{description}</SheetDescription>
                    )}
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-4">
                    {children}
                </div>
                {footer && <SheetFooter>{footer}</SheetFooter>}
            </SheetContent>
        </Sheet>
    );
}
import { XIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';


type ResponsiveOverlayProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    /** Extra classes for the sheet/drawer surface. */
    className?: string;
};

/** Shared chrome for the dismiss button, matching the `shadcndefault` Cancel. */
const CLOSE_BUTTON =
    'ring-offset-background focus:ring-ring bg-secondary text-secondary-foreground absolute top-4 right-4 inline-flex size-8 items-center justify-center rounded-md shadow-xs transition-colors hover:bg-secondary/80 focus:ring-2 focus:ring-offset-2 focus:outline-hidden';

/**
 * A single overlay: a bottom Drawer on phones, a side Sheet on larger screens.
 *
 * The switch happens in JS via `useIsMobile()` because CSS cannot swap two
 * different Radix roots, and mounting both would trap focus twice.
 *
 * **Why a drawer rather than a page.** NN/g is blunt about it — "we strongly
 * recommend not using a bottom sheet to replace typical page-to-page user
 * flows… a bottom sheet is not intended to be a stable place for users to
 * return to" — but its stated reason is that sheets get navigated *through*
 * (product pages linking to reviews, specs). These overlays do not: each is a
 * short, terminal, single-screen task. Measured on a 390x844 phone the largest
 * of them (create, 6 fields) is 588px tall with no internal overflow, against
 * an 844px viewport, so nothing is being cramped into a sheet.
 *
 * **Dismissal is never gesture-only.** Drag is the native affordance and vaul
 * provides it, but Material, NN/g and the accessibility guidance all require a
 * visible alternative: swipe is ambiguous (it can open the notification drawer)
 * and unavailable to anyone who cannot perform a precise vertical drag. Every
 * overlay here therefore carries an explicit close button *and* a Cancel.
 *
 * The drawer caps at `max-h-[80vh]` and its body scrolls, which matters once the
 * soft keyboard is open — that removes roughly 300px of usable viewport, so the
 * create form does overflow there even though it does not at rest.
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
            <Drawer open={open} onOpenChange={onOpenChange} direction="bottom">
                <DrawerContent className={className}>
                    {/*
                     * DrawerHeader centres its text via a `group-data-…:text-center`
                     * variant, which tailwind-merge will not override because it
                     * only resolves unprefixed conflicts. `!` is required to pull
                     * it back to the sheet's left alignment.
                     */}
                    <DrawerHeader className="relative !text-left">
                        <DrawerTitle>{title}</DrawerTitle>
                        {description && (
                            <DrawerDescription>
                                {description}
                            </DrawerDescription>
                        )}
                        <DrawerClose
                            aria-label="Close"
                            className={CLOSE_BUTTON}
                        >
                            <XIcon className="size-4" />
                        </DrawerClose>
                    </DrawerHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-2">
                        {children}
                    </div>
                    {footer && <DrawerFooter>{footer}</DrawerFooter>}
                </DrawerContent>
            </Drawer>
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
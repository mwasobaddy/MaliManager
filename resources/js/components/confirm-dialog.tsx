import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    onConfirm: () => void;
    confirming?: boolean;
};

/**
 * A centred confirmation gate for reversible-but-consequential actions
 * (suspend, deactivate). Destructive, irreversible deletes use
 * `ConfirmDeleteOverlay` with a password instead — this dialog asks whether
 * you mean it, and the server's toast confirms it happened.
 */
export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    onConfirm,
    confirming = false,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="shadcndefault"
                        onClick={() => onOpenChange(false)}
                        disabled={confirming}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={confirming}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

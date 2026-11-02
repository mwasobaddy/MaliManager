import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The DELETE endpoint for the record being removed. */
    action: string;
    title: string;
    description: ReactNode;
};

/**
 * Confirms a destructive action with the actor's password.
 *
 * Always a centred Modal, never a Sheet: the backdrop blocks the rest of the
 * interface and forces attention on the action, where a sheet reads as another
 * panel and invites a click-through.
 *
 * The modal is not the gate. The route's permission middleware authorizes the
 * action and the `current_password` rule on the FormRequest verifies the actor,
 * so a client that skips this component still cannot delete anything.
 *
 * A wrong password keeps the modal open — Inertia leaves the page on the overlay
 * URL and the error arrives on `errors.password` — and the field is cleared so
 * the next attempt is not a double-submit of the same wrong value.
 */
export default function ConfirmDeleteOverlay({
    open,
    onOpenChange,
    action,
    title,
    description,
}: Props) {
    const form = useForm({ password: '' });

    function confirm(event: FormEvent) {
        event.preventDefault();

        form.delete(action, {
            preserveScroll: true,
            // Success redirects to the list, which drops the overlay param and
            // closes the modal with it. Navigating here would be a second trip.
            onSuccess: () => form.reset(),
            onError: () => form.setData('password', ''),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <form onSubmit={confirm} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="confirm-delete-password">
                            Your password
                        </Label>
                        <Input
                            id="confirm-delete-password"
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            autoFocus
                            required
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="shadcndefault"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

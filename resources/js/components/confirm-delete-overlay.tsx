import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect } from 'react';
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
    /** The endpoint receiving the password (and payload). */
    action: string;
    title: string;
    description: ReactNode;
    /**
     * Bulk operations post the trash module plus row ids alongside the
     * password, so one overlay covers single deletes, bulk deletes and
     * restores. Single deletes pass nothing.
     */
    payload?: Record<string, unknown>;
    /** `delete` for destroys, `post` for restores. */
    method?: 'delete' | 'post';
    submitLabel?: string;
    processingLabel?: string;
    /**
     * Destructive red by default. Restores pass `danger={false}` and get
     * the terracotta primary instead — the verb owns the row, never red.
     */
    danger?: boolean;
    /**
     * Closes the modal on success. Single deletes leave this off: the
     * success redirect drops the overlay param and closes the modal with
     * it. Local-state callers (trash) have no param to drop, so they opt
     * in instead of navigating twice.
     */
    closeOnSuccess?: boolean;
    /** Runs after a successful submit — bulk callers clear the selection. */
    onSuccess?: () => void;
};

/**
 * Confirms a destructive action with the actor's password.
 *
 * Always a centred Modal, never a Sheet: the backdrop blocks the rest of the
 * interface and forces attention on the action, where a sheet reads as
 * another panel and invites a click-through.
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
    payload = {},
    method = 'delete',
    submitLabel = 'Delete',
    processingLabel = 'Deleting…',
    danger = true,
    closeOnSuccess = false,
    onSuccess,
}: Props) {
    const form = useForm({ password: '', ...payload });

    // A new target (different rows, module or verb) starts with a blank
    // password: a previous wrong value is a decision about that attempt,
    // not this one.
    useEffect(() => {
        if (open) {
            form.setData({ password: '', ...payload });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function confirm(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            // Success redirects to the list, which drops the overlay param and
            // closes the modal with it. Navigating here would be a second trip.
            onSuccess: () => {
                form.reset();
                onSuccess?.();

                if (closeOnSuccess) {
                    onOpenChange(false);
                }
            },
            onError: () => form.setData('password', ''),
        };

        if (method === 'post') {
            form.post(action, options);
        } else {
            form.delete(action, options);
        }
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
                            variant={danger ? 'destructive' : 'default'}
                            disabled={form.processing}
                        >
                            {form.processing ? processingLabel : submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

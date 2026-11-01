import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Durations per v2 §6.6: advisory toasts clear themselves, errors do not.
 *
 * A success message is read and dismissed; leaving it on screen only stacks up
 * with the next one. An error usually needs a decision — the user has to
 * re-read the field, retry the submit or copy the message — so it stays until
 * it is explicitly closed. `Infinity` is Sonner's way of saying "no timer";
 * the toast still gets a close button, so it is dismissible rather than stuck.
 */
const TOAST_DURATION_MS = {
    success: 8_000,
    info: 8_000,
    warning: 8_000,
    error: Number.POSITIVE_INFINITY,
} satisfies Record<FlashToast['type'], number>;

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message, {
                duration: TOAST_DURATION_MS[data.type],
            });
        });
    }, []);
}

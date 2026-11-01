import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Field-level validation message.
 *
 * Uses `--status-danger` rather than `text-red-600 dark:text-red-400`. This is
 * the component every form in the app renders its errors through, so the raw
 * Tailwind pair meant the app's error colour was defined in two places and
 * could not follow the palette.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn('text-status-danger text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}

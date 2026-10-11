import { CircleCheck, CirclePause, TriangleAlert } from 'lucide-react';
import { ActionIconButton } from '@/components/action-icon-button';
import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'muted';

/**
 * Glyph colour only — no tinted chip. A filled circle behind every status reads
 * as a badge in miniature and adds visual noise to a dense table; the icon
 * shape already carries the meaning.
 */
const toneClasses: Record<StatusTone, string> = {
    success: 'text-status-success',
    warning: 'text-status-warning',
    danger: 'text-status-danger',
    info: 'text-action-view',
    muted: 'text-muted-foreground',
};

/**
 * Status shown as an icon with a tooltip rather than a filled badge.
 *
 * `ui-design-system.md` §1 requires never conveying meaning by colour alone —
 * the icon shape carries the meaning and the tooltip spells out the word, so
 * the state survives greyscale, colour-blindness and screen readers.
 */
export function StatusIcon({
    tone,
    label,
    className,
}: {
    tone: StatusTone;
    label: string;
    className?: string;
}) {
    const Icon =
        tone === 'success'
            ? CircleCheck
            : tone === 'warning'
              ? CirclePause
              : tone === 'danger'
                ? TriangleAlert
                : CirclePause;

    return (
        <span
            aria-label={label}
            role="img"
            className={cn(
                'inline-flex size-6 items-center justify-center',
                toneClasses[tone],
                className,
            )}
        >
            <Icon className="size-4" aria-hidden />
        </span>
    );
}

/**
 * Icon-only status cell. The icon is the button so the state can be clicked
 * to toggle it; the tooltip carries the full sentence.
 */
export function StatusToggleButton({
    tone,
    label,
    actionLabel,
    onClick,
    disabled,
}: {
    tone: StatusTone;
    label: string;
    actionLabel: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <ActionIconButton
            color="default"
            className={cn(toneClasses[tone], 'hover:opacity-75')}
            icon={<StatusIcon tone={tone} label={label} />}
            label={actionLabel}
            onClick={onClick}
            disabled={disabled}
        />
    );
}
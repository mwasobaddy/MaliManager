import { CircleCheck, CirclePause, TriangleAlert } from 'lucide-react';
import { ActionIconButton } from '@/components/action-icon-button';
import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'muted';

const toneClasses: Record<StatusTone, string> = {
    success: 'bg-status-success/12 text-status-success',
    warning: 'bg-status-warning/12 text-status-warning',
    danger: 'bg-status-danger/12 text-status-danger',
    info: 'bg-action-view/12 text-action-view',
    muted: 'bg-muted text-muted-foreground',
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
                'inline-flex size-7 items-center justify-center rounded-md',
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
            className={cn(toneClasses[tone], 'hover:brightness-95')}
            icon={<StatusIcon tone={tone} label={label} />}
            label={actionLabel}
            onClick={onClick}
            disabled={disabled}
        />
    );
}
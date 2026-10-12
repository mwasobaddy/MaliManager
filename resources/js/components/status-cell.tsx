import {
    CircleCheck,
    CirclePause,
    Power,
    PowerOff,
    TriangleAlert,
} from 'lucide-react';
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
 * The status *control* for a table's action cell.
 *
 * Split deliberately from `StatusIcon`: the status column only reports state,
 * and this button in the action cell is what changes it. Putting the toggle
 * in the status column made the column look editable while the real affordance
 * was three columns away.
 *
 * The glyph carries the state rather than just colouring it — `Power` for
 * active, `PowerOff` for suspended — so the meaning survives greyscale and
 * colour-blindness, which is the same rule `ui-design-system.md` §1 sets for
 * status generally. Colour reinforces it: green when active, red when
 * suspended.
 */
export function StatusPowerToggle({
    status,
    activeLabel,
    inactiveLabel,
    onClick,
    disabled,
}: {
    status: string;
    /** Sentence the tooltip reads while the row is active, e.g. "Suspend X". */
    activeLabel: string;
    /** Sentence the tooltip reads while the row is inactive. */
    inactiveLabel: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    const isActive = status === 'active';

    return (
        <ActionIconButton
            color="default"
            className={cn(
                isActive
                    ? 'bg-status-success/12 text-status-success hover:bg-status-success/20 focus-visible:ring-status-success/30'
                    : 'bg-status-danger/12 text-status-danger hover:bg-status-danger/20 focus-visible:ring-status-danger/30',
            )}
            icon={isActive ? <Power /> : <PowerOff />}
            label={isActive ? activeLabel : inactiveLabel}
            tooltipSide="top"
            onClick={onClick}
            disabled={disabled}
        />
    );
}

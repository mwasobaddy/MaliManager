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

/** Tones a *toggle* can wear — `info` has no status token and `muted` reads as broken. */
export type ToggleTone = Exclude<StatusTone, 'info' | 'muted'>;

/**
 * Glyph colour only — no tinted chip. A filled circle behind every status reads
 * as a badge in miniature and adds visual noise to a dense table; the icon
 * shape already carries the meaning.
 */
const toneClasses: Record<StatusTone, string> = {
    success: 'text-status-success',
    warning: 'text-status-warning',
    danger: 'text-status-danger',
    info: 'text-status-info',
    muted: 'text-status-neutral',
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
 * The full class triple for a toggle tone.
 *
 * Written out rather than interpolated from the tone name on purpose: Tailwind
 * extracts class names by scanning source text, so `` `bg-status-${tone}/12` ``
 * never makes it into the build and the rule silently disappears.
 *
 * Neutral at rest, tinted on hover, like every other row action (§7.3) — this
 * lives in the action cell, not the status column, and a column of coloured
 * chips repeated on every row reads as decoration rather than information.
 *
 * `info` is excluded because no toggle wears it, and `--status-info` has no
 * `-bg` pair to tint a chip against; `StatusIcon` uses the flat token instead.
 */
const TOGGLE_TONE_CLASSES = {
    success: 'bg-muted text-muted-foreground hover:bg-status-success-bg hover:text-status-success focus-visible:ring-status-success/30',
    warning: 'bg-muted text-muted-foreground hover:bg-status-warning-bg hover:text-status-warning focus-visible:ring-status-warning/30',
    danger: 'bg-muted text-muted-foreground hover:bg-status-danger-bg hover:text-status-danger focus-visible:ring-status-danger/30',
} satisfies Record<Exclude<StatusTone, 'info' | 'muted'>, string>;

/**
 * The status *control* for a table's action cell.
 *
 * Split deliberately from `StatusIcon`: the status column only reports state,
 * and this button in the action cell is what changes it. Putting the toggle
 * in the status column made the column look editable while the real affordance
 * was three columns away.
 *
 * The glyph carries the state rather than just colouring it — `Power` for
 * active, `PowerOff` for inactive — so the meaning survives greyscale and
 * colour-blindness, which is the same rule `ui-design-system.md` §1 sets for
 * status generally.
 *
 * The tones are overridable because "inactive" is not one meaning. For users
 * and organizations it means *suspended*, which is a red flag and defaults to
 * `danger`. For plans it means the tier is off sale, which is an ordinary
 * merchandising choice and reads as a warning instead.
 */
export function StatusPowerToggle({
    status,
    activeLabel,
    inactiveLabel,
    onClick,
    disabled,
    activeTone = 'success',
    inactiveTone = 'danger',
}: {
    status: string;
    /** Sentence the tooltip reads while the row is active, e.g. "Suspend X". */
    activeLabel: string;
    /** Sentence the tooltip reads while the row is inactive. */
    inactiveLabel: string;
    onClick: () => void;
    disabled?: boolean;
    activeTone?: ToggleTone;
    inactiveTone?: ToggleTone;
}) {
    const isActive = status === 'active';

    return (
        <ActionIconButton
            color="default"
            className={TOGGLE_TONE_CLASSES[isActive ? activeTone : inactiveTone]}
            icon={isActive ? <Power /> : <PowerOff />}
            label={isActive ? activeLabel : inactiveLabel}
            tooltipSide="top"
            onClick={onClick}
            disabled={disabled}
        />
    );
}

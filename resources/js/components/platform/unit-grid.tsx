import { Plus } from 'lucide-react';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import type { UnitGridBuilding } from './types';

type UnitGridCardProps = {
    grid: UnitGridBuilding[];
    /** ISO currency code for the rent line; omitted hides it. */
    currency?: string;
    /** When set, a platform-style add button sits in the header. */
    onAdd?: () => void;
    /** Plan-limit (or other) reason: disables the button, tooltip carries it. */
    addDisabledReason?: string | null;
    addLabel?: string;
    addTooltip?: string;
};

export function UnitGridCard({
    grid,
    currency,
    onAdd,
    addDisabledReason,
    addLabel = 'Add unit',
    addTooltip = 'Add a unit here',
}: UnitGridCardProps) {
    const fill: Record<string, string> = {
        occupied: 'bg-status-success-bg text-status-success-fg',
        vacant: 'bg-status-neutral-bg text-status-neutral-fg',
        maintenance: 'bg-status-warning-bg text-status-warning-fg',
    };

    const suffix = (unit: UnitGridBuilding['units'][number]): string =>
        unit.rent_frequency === 'daily'
            ? '/day'
            : unit.rent_frequency === 'weekly'
              ? '/wk'
              : unit.rent_frequency === 'yearly'
                ? '/yr'
                : '/mo';

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle className="text-base font-medium">
                            Unit map
                        </CardTitle>
                        <CardDescription>
                            Every unit at a glance — the red ring marks arrears.
                        </CardDescription>
                    </div>
                    {onAdd && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label={addLabel}
                            tooltip={addTooltip}
                            color="primary"
                            onClick={onAdd}
                            disabled={addDisabledReason != null}
                            disabledReason={addDisabledReason}
                            className="ml-auto max-w-20 sm:max-w-24"
                        />
                    )}
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {grid.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No units in scope.
                    </p>
                )}
                {grid.map((building) => (
                    <div key={building.property}>
                        <p className="mb-2 text-sm font-medium">
                            {building.property}
                        </p>
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-8">
                            {building.units.map((unit) => (
                                <div
                                    key={unit.id}
                                    title={`${unit.name} · ${unit.type ?? unit.status}${unit.rent_amount && currency ? ` · ${formatMoney(unit.rent_amount, currency)}${suffix(unit)}` : ''}${unit.occupant ? ` · ${unit.occupant}` : ''}`}
                                    className={`rounded-lg border px-2 py-2 text-center ${fill[unit.status] ?? 'bg-muted text-muted-foreground'} ${unit.overdue ? 'ring-2 ring-status-danger' : ''}`}
                                >
                                    <p className="truncate text-xs font-semibold">
                                        {unit.name}
                                    </p>
                                    <p className="truncate text-[10px] opacity-80">
                                        {unit.type ??
                                            unit.occupant ??
                                            unit.status}
                                    </p>
                                    {unit.rent_amount !== null && currency && (
                                        <p className="truncate text-[10px] font-semibold tabular-nums">
                                            {formatMoney(
                                                unit.rent_amount,
                                                currency,
                                            )}
                                            {suffix(unit)}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

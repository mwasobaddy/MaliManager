import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { UnitGridBuilding } from './types';

export function UnitGridCard({ grid }: { grid: UnitGridBuilding[] }) {
    const fill: Record<string, string> = {
        occupied: 'bg-status-success-bg text-status-success-fg',
        vacant: 'bg-status-neutral-bg text-status-neutral-fg',
        maintenance: 'bg-status-warning-bg text-status-warning-fg',
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Unit map
                </CardTitle>
                <CardDescription>
                    Every unit at a glance — the red ring marks arrears.
                </CardDescription>
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
                                    title={`${unit.name} · ${unit.status}${unit.occupant ? ` · ${unit.occupant}` : ''}`}
                                    className={`rounded-lg border px-2 py-2 text-center ${fill[unit.status] ?? 'bg-muted text-muted-foreground'} ${unit.overdue ? 'ring-2 ring-status-danger' : ''}`}
                                >
                                    <p className="truncate text-xs font-semibold">
                                        {unit.name}
                                    </p>
                                    <p className="truncate text-[10px] opacity-80">
                                        {unit.occupant ?? unit.status}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

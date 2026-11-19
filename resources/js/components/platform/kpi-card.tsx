import { CardContent } from '@/components/ui/card';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * Six-point sparkline for KPIs with honest history. Decorative: the delta
 * text next to it carries the meaning, so screen readers and greyscale
 * lose nothing.
 */
export function Sparkline({ points }: { points: number[] }) {
    if (points.length < 2) {
        return null;
    }

    const width = 96;
    const height = 28;
    const max = Math.max(...points, 1);
    const step = width / (points.length - 1);
    const path = points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${(index * step).toFixed(1)},${(height - 2 - (point / max) * (height - 4)).toFixed(1)}`,
        )
        .join(' ');

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-7 w-24"
            aria-hidden
        >
            <path
                d={path}
                fill="none"
                stroke="var(--chart-1)"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * Snapshot-sourced sparkline points: two or more snapshots or nothing.
 * A single point is not a trend, and month-to-date counters never spark
 * (they sawtooth on the 1st) — those stay delta text.
 */
export function snapshotSpark(values?: number[]): number[] | undefined {
    return values !== undefined && values.length >= 2 ? values : undefined;
}

export function KpiCard({
    label,
    value,
    sub,
    alert,
    spark,
}: {
    label: string;
    value: string;
    sub: string;
    alert?: boolean;
    spark?: number[];
}) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-1 pt-5">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={cn(
                        'text-2xl font-semibold tracking-tight tabular-nums',
                        alert && 'text-status-danger',
                    )}
                >
                    {value}
                </p>
                <div className="flex items-end justify-between gap-2">
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {sub}
                    </p>
                    {spark && <Sparkline points={spark} />}
                </div>
            </CardContent>
        </Card>
    );
}

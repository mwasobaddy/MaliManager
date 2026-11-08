import { BarChart3, Table2 } from 'lucide-react';
import { useId, useState } from 'react';
import {
    Area,
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SampleBadge } from './sample-badge';

export type ComboSeries = {
    key: string;
    label: string;
    color: string;
    type: 'line' | 'area' | 'bar';
    axis?: 'left' | 'right';
    sample?: boolean;
};

export type ComboPoint = Record<string, string | number>;

type Props = {
    title: string;
    data: ComboPoint[];
    series: ComboSeries[];
    height?: number;
};

/**
 * Trend chart with a table fallback.
 *
 * §7.5 asks for two accessibility affordances that a chart library cannot give
 * on its own:
 *
 * - `accessibilityLayer` makes the SVG focusable and keyboard-navigable, so
 *   arrow keys walk the data points instead of the whole chart being one
 *   opaque graphic.
 * - "View as table" is the honest fallback. A screen-reader user can read a
 *   table; a colour-blind user can read exact values instead of relying on
 *   hue. It is the same data, not a summary of it.
 */
export function ComboChart({ title, data, series, height = 280 }: Props) {
    const hasRightAxis = series.some((s) => s.axis === 'right');
    const isSample = series.some((s) => s.sample);
    const [view, setView] = useState<'chart' | 'table'>('chart');
    const tableId = useId();

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base font-medium">
                        {title}
                    </CardTitle>
                    {isSample && <SampleBadge />}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="gap-1.5 text-muted-foreground hover:text-foreground"
                    onClick={() =>
                        setView((current) =>
                            current === 'chart' ? 'table' : 'chart',
                        )
                    }
                    aria-pressed={view === 'table'}
                    aria-controls={view === 'table' ? tableId : undefined}
                >
                    {view === 'chart' ? (
                        <Table2 className="size-4" aria-hidden />
                    ) : (
                        <BarChart3 className="size-4" aria-hidden />
                    )}
                    {view === 'chart' ? 'View as table' : 'View as chart'}
                </Button>
            </CardHeader>

            <CardContent>
                {view === 'chart' ? (
                    <ResponsiveContainer width="100%" height={height}>
                        <ComposedChart
                            data={data}
                            margin={{ left: -10, right: 10, top: 6 }}
                            accessibilityLayer
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                className="stroke-border"
                            />
                            <XAxis
                                dataKey="label"
                                tick={{ fontSize: 11 }}
                                className="fill-muted-foreground"
                            />
                            <YAxis
                                yAxisId="left"
                                tick={{ fontSize: 11 }}
                                className="fill-muted-foreground"
                            />
                            {hasRightAxis && (
                                <YAxis
                                    yAxisId="right"
                                    orientation="right"
                                    tick={{ fontSize: 11 }}
                                    className="fill-muted-foreground"
                                />
                            )}
                            <Tooltip />
                            <Legend />
                            {series.map((s) => {
                                const yAxisId = s.axis ?? 'left';

                                if (s.type === 'area') {
                                    return (
                                        <Area
                                            key={s.key}
                                            dataKey={s.key}
                                            name={s.label}
                                            stroke={s.color}
                                            fill={s.color}
                                            fillOpacity={0.2}
                                            yAxisId={yAxisId}
                                            type="monotone"
                                        />
                                    );
                                }

                                if (s.type === 'line') {
                                    return (
                                        <Line
                                            key={s.key}
                                            dataKey={s.key}
                                            name={s.label}
                                            stroke={s.color}
                                            yAxisId={yAxisId}
                                            type="monotone"
                                            dot={false}
                                        />
                                    );
                                }

                                return (
                                    <Bar
                                        key={s.key}
                                        dataKey={s.key}
                                        name={s.label}
                                        fill={s.color}
                                        yAxisId={yAxisId}
                                        radius={[4, 4, 0, 0]}
                                    />
                                );
                            })}
                        </ComposedChart>
                    </ResponsiveContainer>
                ) : (
                    <div
                        id={tableId}
                        className="max-h-[280px] overflow-auto rounded-md border"
                    >
                        <table className="w-full border-collapse text-sm">
                            <caption className="sr-only">
                                {title} — the same data as the chart, as a table
                            </caption>
                            <thead>
                                <tr className="sticky top-0 border-b border-border-strong bg-bg-base">
                                    {[
                                        'Period',
                                        ...series.map((s) => s.label),
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            scope="col"
                                            className="px-3 py-2 text-right text-xs font-medium tracking-wide text-muted-foreground uppercase first:text-left"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((point, index) => (
                                    <tr
                                        key={index}
                                        className="border-b border-border/60 last:border-b-0"
                                    >
                                        <th
                                            scope="row"
                                            className="px-3 py-1.5 text-left font-normal text-foreground"
                                        >
                                            {point.label}
                                        </th>
                                        {series.map((s) => (
                                            <td
                                                key={s.key}
                                                className="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                {formatValue(point[s.key])}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Numbers get thousands separators; anything else renders as-is. */
function formatValue(value: string | number | undefined): string {
    if (value === undefined || value === null) {
        return '—';
    }

    return typeof value === 'number'
        ? value.toLocaleString(undefined, { maximumFractionDigits: 2 })
        : value;
}

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

export function ComboChart({ title, data, series, height = 280 }: Props) {
    const hasRightAxis = series.some((s) => s.axis === 'right');
    const isSample = series.some((s) => s.sample);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-base font-medium">{title}</CardTitle>
                {isSample && <SampleBadge />}
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={height}>
                    <ComposedChart
                        data={data}
                        margin={{ left: -10, right: 10, top: 6 }}
                    >
                        <CartesianGrid
                            strokeDasharray="3 3"
                            className="stroke-muted"
                        />
                        <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                        <YAxis yAxisId="left" tick={{ fontSize: 11 }} />
                        {hasRightAxis && (
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                tick={{ fontSize: 11 }}
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
            </CardContent>
        </Card>
    );
}

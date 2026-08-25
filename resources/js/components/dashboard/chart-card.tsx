import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SampleBadge } from './sample-badge';

export type SeriesPoint = {
    label: string;
    value: number;
    sample?: boolean;
};

type Props = {
    title: string;
    data: SeriesPoint[];
    type?: 'area' | 'line' | 'bar';
    sample?: boolean;
    color?: string;
    height?: number;
};

export function ChartCard({
    title,
    data,
    type = 'area',
    sample = false,
    color = '#2563eb',
    height = 260,
}: Props) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-base font-medium">{title}</CardTitle>
                {sample && <SampleBadge />}
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={height}>
                    {type === 'area' ? (
                        <AreaChart data={data} margin={{ left: -20, right: 10, top: 6 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                            <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                            <YAxis tick={{ fontSize: 11 }} />
                            <Tooltip />
                            <Area
                                type="monotone"
                                dataKey="value"
                                stroke={color}
                                fill={color}
                                fillOpacity={0.2}
                            />
                        </AreaChart>
                    ) : type === 'line' ? (
                        <LineChart data={data} margin={{ left: -20, right: 10, top: 6 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                            <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                            <YAxis tick={{ fontSize: 11 }} />
                            <Tooltip />
                            <Line type="monotone" dataKey="value" stroke={color} />
                        </LineChart>
                    ) : (
                        <BarChart data={data} margin={{ left: -20, right: 10, top: 6 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                            <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                            <YAxis tick={{ fontSize: 11 }} />
                            <Tooltip />
                            <Bar dataKey="value" fill={color} radius={[4, 4, 0, 0]} />
                        </BarChart>
                    )}
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}

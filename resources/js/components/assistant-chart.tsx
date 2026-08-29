import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export type AssistantArtifact = {
    kind: 'query' | 'search';
    chart: 'bar' | 'pie' | 'line' | 'table' | null;
    entity: string;
    group_by: string[];
    measures: { type: string; column?: string }[];
    rows: Record<string, string | number>[];
    row_count: number;
};

const PIE_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

function measureKeys(measures: { type: string; column?: string }[]): string[] {
    return measures.map((m) => (m.type === 'count' ? 'count' : `${m.type}_${m.column}`));
}

function measureLabel(m: { type: string; column?: string }): string {
    if (m.type === 'count') {
        return 'Count';
    }

    const column = m.column ?? '';

    return `${m.type === 'sum' ? 'Total' : 'Avg'} ${column.replace(/_/g, ' ')}`;
}

export function AssistantChart({ artifact }: { artifact: AssistantArtifact }) {
    const { chart, rows, measures, group_by: groupBy } = artifact;

    if (chart === 'table' || chart === null) {
        if (chart === null && groupBy.length === 0) {
            return (
                <div className="flex flex-wrap gap-2">
                    {rows[0] &&
                        Object.entries(rows[0]).map(([key, value]) => (
                            <span
                                key={key}
                                className="rounded-md bg-accent px-2 py-1 text-xs text-accent-foreground"
                            >
                                <span className="font-medium capitalize">{key.replace(/_/g, ' ')}:</span>{' '}
                                {String(value)}
                            </span>
                        ))}
                </div>
            );
        }

        const columns = rows[0] ? Object.keys(rows[0]) : [];

        return (
            <div className="max-h-64 overflow-auto rounded-md border border-input">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="bg-muted">
                            {columns.map((column) => (
                                <th key={column} className="px-2 py-1 text-left font-medium capitalize">
                                    {column.replace(/_/g, ' ')}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={index} className="border-t border-input">
                                {columns.map((column) => (
                                    <td key={column} className="px-2 py-1">
                                        {String(row[column])}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    if (rows.length === 0) {
        return <p className="text-xs text-muted-foreground">No rows returned.</p>;
    }

    const keys = measureKeys(measures);
    const category = groupBy[0] ?? 'value';

    if (chart === 'pie') {
        const dataKey = keys[0];

        return (
            <ResponsiveContainer width="100%" height={260}>
                <PieChart>
                    <Pie
                        data={rows}
                        dataKey={dataKey}
                        nameKey={category}
                        label={(entry: { [key: string]: string | number }) =>
                            `${String(entry[category])} (${String(entry[dataKey])})`
                        }
                    >
                        {rows.map((_, index) => (
                            <Cell key={index} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                        ))}
                    </Pie>
                    <Tooltip />
                    <Legend />
                </PieChart>
            </ResponsiveContainer>
        );
    }

    const Chart = chart === 'line' ? LineChart : BarChart;
    const Glyph = chart === 'line' ? Line : Bar;

    return (
        <ResponsiveContainer width="100%" height={260}>
            <Chart data={rows} margin={{ top: 8, right: 8, bottom: 8, left: 8 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis dataKey={category} tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Legend />
                {keys.map((key, index) => (
                    <Glyph key={key} type={chart === 'line' ? 'monotone' : undefined} dataKey={key} name={measureLabel(measures[index])} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                ))}
            </Chart>
        </ResponsiveContainer>
    );
}

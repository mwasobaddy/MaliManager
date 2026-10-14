import { Head } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    csv as csvRoute,
    data as dataRoute,
    print as printRoute,
} from '@/routes/tenant/reports';

type Column = { key: string; label: string };

type ReportPayload = {
    type: string;
    title: string;
    columns: Column[];
    rows: Record<string, unknown>[];
    summary: Record<string, string | number>;
};

type Props = {
    types: string[];
};

const REPORT_LABELS: Record<string, string> = {
    occupancy: 'Occupancy',
    leases: 'Lease pipeline',
    expenses: 'Expenses',
    maintenance: 'Maintenance',
    market: 'Internal market analysis',
};

const PRESETS = [
    { label: 'Last 30 days', days: 30 },
    { label: 'Last 90 days', days: 90 },
    { label: 'Last 365 days', days: 365 },
    { label: 'All time', days: null },
];

function isoDaysAgo(days: number | null): string {
    return days === null
        ? ''
        : new Date(Date.now() - days * 86_400_000).toISOString().slice(0, 10);
}

export default function ReportsIndex({ types }: Props) {
    const [type, setType] = useState<string>(types[0] ?? 'occupancy');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [report, setReport] = useState<ReportPayload | null>(null);
    const [loading, setLoading] = useState(true);

    // Current query shared by table + CSV + print so they always match.
    const query = () => ({
        type,
        ...(from ? { from } : {}),
        ...(to ? { to } : {}),
    });

    useEffect(() => {
        let cancelled = false;

        fetch(dataRoute.url({ query: query() }), {
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((data) => {
                if (!cancelled) {
                    setReport(data);
                }
            })
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [type, from, to]);

    const applyPreset = (days: number | null) => {
        setFrom(isoDaysAgo(days));
        setTo(days === null ? '' : new Date().toISOString().slice(0, 10));
    };

    return (
        <>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Reports"
                        description="Portfolio datasets with date filtering and exports."
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Report" />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {REPORT_LABELS[t] ?? t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                window.open(
                                    printRoute.url({ query: query() }),
                                    '_blank',
                                )
                            }
                        >
                            <Printer className="size-4" />
                            Print / PDF
                        </Button>
                        <Button
                            size="sm"
                            onClick={() =>
                                window.open(csvRoute.url({ query: query() }))
                            }
                        >
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-input p-4">
                    <div className="flex gap-1">
                        {PRESETS.map((preset) => (
                            <Button
                                key={preset.label}
                                variant="outline"
                                size="sm"
                                onClick={() => applyPreset(preset.days)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                    <div className="grid gap-1">
                        <Label
                            htmlFor="from"
                            className="text-xs text-muted-foreground"
                        >
                            From
                        </Label>
                        <Input
                            id="from"
                            type="date"
                            className="w-40"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label
                            htmlFor="to"
                            className="text-xs text-muted-foreground"
                        >
                            To
                        </Label>
                        <Input
                            id="to"
                            type="date"
                            className="w-40"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                </div>

                <Card>
                    {loading || !report ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Loading…
                        </p>
                    ) : report.rows.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No data for this range.
                        </p>
                    ) : (
                        <>
                            {Object.keys(report.summary).length > 0 && (
                                <div className="flex flex-wrap gap-6 px-4 pt-4">
                                    {Object.entries(report.summary).map(
                                        ([label, value]) => (
                                            <div key={label}>
                                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                                    {label}
                                                </p>
                                                <p className="text-lg font-semibold tabular-nums">
                                                    {value}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                            <div className="overflow-x-auto p-4">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            {report.columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    className="px-3 py-2"
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.rows.map((row, index) => (
                                            <tr
                                                key={index}
                                                className="border-b last:border-0"
                                            >
                                                {report.columns.map(
                                                    (column) => (
                                                        <td
                                                            key={column.key}
                                                            className="px-3 py-2 capitalize"
                                                        >
                                                            {String(
                                                                row[
                                                                    column.key
                                                                ] ?? '—',
                                                            )}
                                                        </td>
                                                    ),
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </Card>
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

type Column = { key: string; label: string };

type Props = {
    report: {
        title: string;
        columns: Column[];
        rows: Record<string, unknown>[];
        summary: Record<string, string | number>;
    };
    organizationName: string | null;
    rangeLabel: string;
};

export default function ReportPrint({
    report,
    organizationName,
    rangeLabel,
}: Props) {
    return (
        <>
            <Head title={`${report.title} — ${organizationName ?? ''}`} />
            <div className="mx-auto max-w-4xl p-8 print:p-0">
                <div className="mb-6 flex items-center justify-between print:hidden">
                    <h1 className="text-lg font-semibold">{report.title}</h1>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        data-test="print-report-button"
                    >
                        <Printer className="size-4" />
                        Print / Save as PDF
                    </button>
                </div>

                <header className="mb-6 border-b pb-4 print:mb-4">
                    <h2 className="text-xl font-semibold">{report.title}</h2>
                    <p className="text-sm text-muted-foreground">
                        {organizationName} · {rangeLabel}
                    </p>
                    {Object.keys(report.summary).length > 0 && (
                        <dl className="mt-3 flex flex-wrap gap-6 text-sm">
                            {Object.entries(report.summary).map(
                                ([label, value]) => (
                                    <div key={label}>
                                        <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                            {label}
                                        </dt>
                                        <dd className="font-semibold tabular-nums">
                                            {value}
                                        </dd>
                                    </div>
                                ),
                            )}
                        </dl>
                    )}
                </header>

                {report.rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No data for this range.
                    </p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-xs tracking-wide text-muted-foreground uppercase">
                                {report.columns.map((column) => (
                                    <th key={column.key} className="px-3 py-2">
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row, index) => (
                                <tr
                                    key={index}
                                    className="break-inside-avoid border-b"
                                >
                                    {report.columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className="px-3 py-2 capitalize"
                                        >
                                            {String(row[column.key] ?? '—')}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}

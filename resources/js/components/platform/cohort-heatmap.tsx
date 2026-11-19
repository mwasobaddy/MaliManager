import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Growth } from './types';

/**
 * Cohort retention as a plain table, not a chart library heatmap: exact
 * values survive screen readers and greyscale, and no new dependency is
 * needed. Future months read as empty, never as zero.
 */
export function CohortHeatmap({ cohorts }: { cohorts: Growth['cohorts'] }) {
    const tint = (rate: number | null): string => {
        if (rate === null) {
            return 'bg-muted/40 text-muted-foreground';
        }

        if (rate >= 90) {
            return 'bg-brand-accent/80 text-white';
        }

        if (rate >= 70) {
            return 'bg-brand-accent/60 text-white';
        }

        if (rate >= 50) {
            return 'bg-brand-accent/40 text-foreground';
        }

        if (rate >= 25) {
            return 'bg-brand-accent/20 text-foreground';
        }

        return 'bg-brand-accent/10 text-foreground';
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Cohort retention
                </CardTitle>
                <CardDescription>
                    Share of each signup month holding subscription cover in
                    later months.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">Cohort</th>
                            <th className="px-4 py-2 font-medium">Size</th>
                            {cohorts.columns.map((column) => (
                                <th
                                    key={column}
                                    className="px-4 py-2 text-center font-medium"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {cohorts.rows.map((row) => (
                            <tr
                                key={row.cohort}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2 font-medium whitespace-nowrap">
                                    {row.cohort}
                                </td>
                                <td className="px-4 py-2 tabular-nums">
                                    {row.size}
                                </td>
                                {row.cells.map((cell, index) => (
                                    <td
                                        key={`${row.cohort}-${cohorts.columns[index]}`}
                                        className="px-2 py-1 text-center"
                                    >
                                        <span
                                            className={cn(
                                                'inline-block min-w-14 rounded-md px-2 py-1 text-xs font-medium tabular-nums',
                                                tint(cell.rate),
                                            )}
                                            title={
                                                cell.rate === null
                                                    ? 'Not yet knowable'
                                                    : `${cell.retained} of ${row.size} retained (${cell.rate}%)`
                                            }
                                        >
                                            {cell.rate === null
                                                ? '—'
                                                : `${cell.rate}%`}
                                        </span>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

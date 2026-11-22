import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import type { AgingRow } from './types';

export function ArrearsAgingCard({ aging }: { aging: AgingRow[] }) {
    const max = Math.max(...aging.map((row) => Number(row.total)), 1);

    return (
        <Card className='h-full'>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Arrears aging
                </CardTitle>
                <CardDescription>
                    Overdue shillings by how late they are.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {aging.map((row) => (
                    <div key={row.bucket} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{row.bucket}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatMoney(row.total)} · {row.count}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${row.bucket}: ${formatMoney(row.total)} across ${row.count} leases`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-2"
                                style={{
                                    width: `${(Number(row.total) / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

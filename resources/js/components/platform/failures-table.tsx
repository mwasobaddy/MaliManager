import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import type { Payments } from './types';

export function FailuresTable({
    failures,
    currency,
}: {
    failures: Payments['failures'];
    currency: string;
}) {
    if (failures.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Recent failures
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No failed gateway attempts on record.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Recent failures
                </CardTitle>
                <CardDescription>
                    Failed subscription payment attempts, newest first.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">
                                Organization
                            </th>
                            <th className="px-4 py-2 font-medium">Gateway</th>
                            <th className="px-4 py-2 font-medium">Amount</th>
                            <th className="px-4 py-2 font-medium">Reason</th>
                            <th className="px-4 py-2 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        {failures.map((failure, index) => (
                            <tr
                                key={`${failure.organization}-${failure.age}-${index}`}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2.5 font-medium">
                                    {failure.organization}
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge variant="neutral">
                                        {failure.gateway}
                                    </Badge>
                                </td>
                                <td className="px-4 py-2.5 tabular-nums">
                                    {formatMoney(failure.amount, currency)}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground">
                                    {failure.reason}
                                </td>
                                <td className="px-4 py-2.5 whitespace-nowrap text-muted-foreground">
                                    {failure.age}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

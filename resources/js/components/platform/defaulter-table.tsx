import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import { remind as remindLease } from '@/routes/tenant/leases';
import type { DefaulterRow } from './types';

export function DefaulterTable({ defaulters }: { defaulters: DefaulterRow[] }) {
    if (defaulters.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Top defaulters
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        Nobody owes rent right now.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const remind = (propertySlug: string, leaseId: number) => {
        router.post(
            remindLease({ property: propertySlug, lease: leaseId }).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Top defaulters
                </CardTitle>
                <CardDescription>
                    Largest balances first — remind with one tap.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">Tenant</th>
                            <th className="px-4 py-2 font-medium">Owes</th>
                            <th className="px-4 py-2 font-medium">Late by</th>
                            <th className="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {defaulters.map((row) => (
                            <tr
                                key={row.lease_id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">
                                        {row.occupant === ''
                                            ? 'Unnamed tenant'
                                            : row.occupant}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.unit ?? '—'} · {row.property}
                                    </p>
                                </td>
                                <td className="px-4 py-2.5 font-medium tabular-nums">
                                    {formatMoney(row.outstanding)}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground tabular-nums">
                                    {row.days_overdue}d
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end">
                                        {row.can_remind && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    remind(
                                                        row.property_slug,
                                                        row.lease_id,
                                                    )
                                                }
                                            >
                                                Remind
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

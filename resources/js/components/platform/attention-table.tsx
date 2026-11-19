import { Form, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import * as orgRoutes from '@/routes/platform/organizations';
import type { AttentionRow } from './types';

const REASON_LABEL: Record<AttentionRow['reason'], string> = {
    past_due: 'Past due',
    trial_ending: 'Trial ending',
    suspended: 'Suspended',
};

const REASON_BADGE: Record<
    AttentionRow['reason'],
    'danger' | 'warning' | 'neutral'
> = {
    past_due: 'danger',
    trial_ending: 'warning',
    suspended: 'neutral',
};

export function AttentionTable({ rows }: { rows: AttentionRow[] }) {
    if (rows.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Tenants to watch
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        Nothing needs attention.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Tenants to watch
                </CardTitle>
                <CardDescription>
                    Past-due, trials about to lapse, and suspended accounts
                    first — the full list is below.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">
                                Organization
                            </th>
                            <th className="px-4 py-2 font-medium">Reason</th>
                            <th className="px-4 py-2 font-medium">Plan</th>
                            <th className="px-4 py-2 font-medium">
                                Last payment
                            </th>
                            <th className="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-b last:border-0">
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">{row.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.slug} ·{' '}
                                        {row.days_past_due !== null
                                            ? `${row.days_past_due}d past due`
                                            : row.period_ends_at
                                              ? `cover ends ${row.period_ends_at}`
                                              : row.status}
                                    </p>
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge variant={REASON_BADGE[row.reason]}>
                                        {REASON_LABEL[row.reason]}
                                    </Badge>
                                </td>
                                <td className="px-4 py-2.5 tabular-nums">
                                    {row.plan ?? 'No plan'}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground tabular-nums">
                                    {row.last_payment
                                        ? `${row.last_payment.amount} · ${row.last_payment.received_on}`
                                        : 'Never paid'}
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end gap-2">
                                        <Form
                                            method="post"
                                            action={orgRoutes.impersonate(
                                                row.id,
                                            )}
                                            as="button"
                                            disabled={
                                                row.status === 'suspended'
                                            }
                                            title={
                                                row.status === 'suspended'
                                                    ? 'Suspended organizations cannot be impersonated'
                                                    : `Log in as ${row.name} owner`
                                            }
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    row.status === 'suspended'
                                                }
                                            >
                                                Impersonate
                                            </Button>
                                        </Form>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link href={orgRoutes.index().url}>
                                                Manage
                                            </Link>
                                        </Button>
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

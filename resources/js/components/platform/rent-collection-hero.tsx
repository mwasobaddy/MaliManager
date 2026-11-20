import { Card, CardContent } from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import type { Collection } from './types';

/**
 * Collected vs expected hero: the one card that answers "am I getting
 * paid". A progress bar, not a number — position in the month matters as
 * much as the total.
 */
export function RentCollectionHero({ collection }: { collection: Collection }) {
    const expected = Number(collection.expected_mtd);
    const collected = Number(collection.collected_mtd);
    const pct = expected > 0 ? Math.min((collected / expected) * 100, 100) : 0;

    return (
        <Card>
            <CardContent className="flex flex-col gap-2 pt-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        Rent collected this month
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {collection.collection_rate === null
                            ? 'no rent due yet'
                            : `${collection.collection_rate}% of expected`}
                    </p>
                </div>
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {formatMoney(collection.collected_mtd)} of{' '}
                    {formatMoney(collection.expected_mtd)}
                </p>
                <div
                    className="h-2.5 overflow-hidden rounded-full bg-muted"
                    role="img"
                    aria-label={`Collected ${formatMoney(collection.collected_mtd)} of ${formatMoney(collection.expected_mtd)} expected`}
                >
                    <div
                        className="h-full rounded-full bg-brand-primary"
                        style={{ width: `${pct}%` }}
                    />
                </div>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {formatMoney(collection.arrears_total)} overdue across{' '}
                    {collection.overdue_count}{' '}
                    {collection.overdue_count === 1 ? 'lease' : 'leases'}
                </p>
            </CardContent>
        </Card>
    );
}

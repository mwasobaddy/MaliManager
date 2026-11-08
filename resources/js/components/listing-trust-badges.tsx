import { Badge } from '@/components/ui/badge';
import type { ListingTrust } from '@/types/guest';

/**
 * Factual trust signals for a public listing. Every badge is derived from
 * data the backend verified — photos on the unit, a stated deposit, the
 * landlord's tenure — never claimed.
 */
export function ListingTrustBadges({ trust }: { trust: ListingTrust }) {
    if (!trust.photos && !trust.deposit && trust.landlord_since === null) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {trust.photos && <Badge variant="neutral">Photos included</Badge>}
            {trust.deposit && <Badge variant="neutral">Deposit stated</Badge>}
            {trust.landlord_since !== null && (
                <Badge variant="neutral">
                    Landlord since {trust.landlord_since}
                </Badge>
            )}
        </div>
    );
}

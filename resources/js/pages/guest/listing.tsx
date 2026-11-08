import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';
import Heading from '@/components/heading';
import { ListingTrustBadges } from '@/components/listing-trust-badges';
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
import { home as guestHome } from '@/routes/guest';
import type { ListedUnitDetail } from '@/types/guest';

type Props = {
    unit: ListedUnitDetail;
};

function fact(label: string, value: string | null) {
    return (
        <div className="flex items-center justify-between gap-2 py-2 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{value ?? '—'}</span>
        </div>
    );
}

export default function Listing({ unit }: Props) {
    const [firstPhoto, ...restPhotos] = unit.photos;

    return (
        <>
            <Head title={`${unit.name} - Find a place`} />

            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={guestHome.url()}>
                        <ArrowLeft className="size-4" />
                        Back to listings
                    </Link>
                </Button>

                <Heading
                    level="h1"
                    title={unit.name}
                    description={
                        [unit.property_name, unit.property_city]
                            .filter(Boolean)
                            .join(' · ') || 'Rental unit'
                    }
                />

                {firstPhoto ? (
                    <div className="space-y-3">
                        <img
                            src={firstPhoto.url}
                            alt={`${unit.name} photo`}
                            className="aspect-video w-full rounded-xl border border-input object-cover"
                            loading="eager"
                        />
                        {restPhotos.length > 0 && (
                            <div className="grid grid-cols-3 gap-3">
                                {restPhotos.map((photo) => (
                                    <img
                                        key={photo.id}
                                        src={photo.url}
                                        alt={`${unit.name} photo`}
                                        className="aspect-video w-full rounded-lg border border-input object-cover"
                                        loading="lazy"
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <div
                        aria-hidden
                        className="flex aspect-video w-full items-center justify-center rounded-xl border border-dashed border-input bg-muted"
                    >
                        <Building2 className="size-10 text-muted-foreground" />
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Rental facts
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y divide-border">
                            {fact(
                                'Monthly rent',
                                unit.monthly_rent !== null
                                    ? formatMoney(
                                          unit.monthly_rent,
                                          unit.currency,
                                      )
                                    : null,
                            )}
                            {fact(
                                'Deposit',
                                unit.deposit !== null
                                    ? formatMoney(unit.deposit, unit.currency)
                                    : null,
                            )}
                            {fact('Unit type', unit.type)}
                            {fact('Property', unit.property_name)}
                            {fact('Area', unit.property_city)}
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base font-medium">
                                    Listed by
                                </CardTitle>
                                {unit.organization_name && (
                                    <CardDescription>
                                        {unit.organization_name}
                                        {unit.landlord_since
                                            ? ` · on MaliManager since ${unit.landlord_since}`
                                            : ''}
                                    </CardDescription>
                                )}
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-2">
                                {unit.type && (
                                    <Badge variant="neutral">{unit.type}</Badge>
                                )}
                                <Badge variant="success">Vacant now</Badge>
                                <ListingTrustBadges trust={unit.trust} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base font-medium">
                                    How renting works
                                </CardTitle>
                                <CardDescription>
                                    There is no online application: the landlord
                                    adds you as an occupant and the lease — and
                                    M-Pesa rent collection — are handled in the
                                    app.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

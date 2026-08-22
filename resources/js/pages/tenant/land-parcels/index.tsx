import { Head, Link } from '@inertiajs/react';
import { Map, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create, show } from '@/routes/tenant/land-parcels';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type LandParcel = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    zoning: string;
};

type Props = {
    organization: Organization;
    land_parcels: LandParcel[];
    canCreateLandParcel: boolean;
};

export default function LandParcelsIndex({ organization, land_parcels, canCreateLandParcel }: Props) {
    return (
        <>
            <Head title="Land parcels" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${organization.name} land parcels`}
                        description="Select a parcel to manage its details, or add a new one."
                    />
                    {canCreateLandParcel && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Add land parcel
                            </Link>
                        </Button>
                    )}
                </div>

                {land_parcels.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Map className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No land parcels yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first parcel to start tracking plots of land.
                            </p>
                            {canCreateLandParcel && (
                                <Button asChild className="mt-6">
                                    <Link href={create()}>Add your first land parcel</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {land_parcels.map((parcel) => (
                            <Link
                                key={parcel.id}
                                href={show(parcel.slug)}
                                className="rounded-xl border border-input bg-card transition-colors hover:bg-muted"
                            >
                                <Card className="border-0 bg-transparent shadow-none">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Map className="size-4 text-muted-foreground" />
                                            <CardTitle>{parcel.name}</CardTitle>
                                        </div>
                                        <CardDescription>
                                            {parcel.city ? `${parcel.city} · ` : ''}
                                            {parcel.zoning}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Badge
                                            variant={parcel.status === 'active' ? 'default' : 'secondary'}
                                        >
                                            {parcel.status}
                                        </Badge>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

LandParcelsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
    ],
};

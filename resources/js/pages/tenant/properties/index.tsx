import { Head, Link } from '@inertiajs/react';
import { Building2, Map, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create as createParcel, show as showParcel } from '@/routes/tenant/land-parcels';
import { create, dashboard } from '@/routes/tenant/properties';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    units_count: number;
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
    properties: Property[];
    land_parcels: LandParcel[];
    canCreateProperty: boolean;
    canManageLandParcels: boolean;
    canCreateLandParcel: boolean;
};

export default function PropertiesIndex({
    organization,
    properties,
    land_parcels,
    canCreateProperty,
    canManageLandParcels,
    canCreateLandParcel,
}: Props) {
    return (
        <>
            <Head title="Your properties" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${organization.name} properties`}
                        description="Select a property to manage its units, or add a new one."
                    />
                    {canCreateProperty && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Add property
                            </Link>
                        </Button>
                    )}
                </div>

                {properties.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Building2 className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No properties yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first property to start managing units.
                            </p>
                            {canCreateProperty && (
                                <Button asChild className="mt-6">
                                    <Link href={create()}>Add your first property</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {properties.map((property) => (
                            <Link
                                key={property.id}
                                href={dashboard(property.slug)}
                                className="rounded-xl border border-input bg-card transition-colors hover:bg-muted"
                            >
                                <Card className="border-0 bg-transparent shadow-none">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Building2 className="size-4 text-muted-foreground" />
                                            <CardTitle>{property.name}</CardTitle>
                                        </div>
                                        <CardDescription>
                                            {property.city ?? 'No city set'}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        {property.units_count}{' '}
                                        {property.units_count === 1 ? 'unit' : 'units'}
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                {canManageLandParcels && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <Heading
                                variant="small"
                                title="Land parcels"
                                description="Plots of land you track separately from buildings."
                            />
                            {canCreateLandParcel && (
                                <Button asChild variant="outline">
                                    <Link href={createParcel()}>
                                        <Plus className="size-4" />
                                        Add land parcel
                                    </Link>
                                </Button>
                            )}
                        </div>

                        {land_parcels.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No land parcels yet.
                            </p>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {land_parcels.map((parcel) => (
                                    <Link
                                        key={parcel.id}
                                        href={showParcel(parcel.slug)}
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
                )}
            </div>
        </>
    );
}

PropertiesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
        },
    ],
};
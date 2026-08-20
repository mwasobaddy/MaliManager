import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, Phone, Plus, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create as createOccupant, edit as editOccupant } from '@/routes/tenant/occupants';

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
};

type Occupant = {
    id: number;
    first_name: string;
    last_name: string | null;
    email: string;
    phone: string | null;
    national_id: string | null;
    status: string;
    units: Unit[];
};

type Props = {
    property: Property;
    occupants: Occupant[];
};

export default function OccupantsIndex({ property, occupants }: Props) {
    const { tenant } = usePage().props;
    const permissions = tenant?.permissions ?? [];
    const canCreate = permissions.includes('occupant.create');
    const canEdit = permissions.includes('occupant.edit');
    const canDelete = permissions.includes('occupant.delete');

    return (
        <>
            <Head title={`${property.name} occupants`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${property.name} occupants`}
                        description="The people renting units in this property."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={createOccupant(property.slug)}>
                                <Plus className="size-4" />
                                Add occupant
                            </Link>
                        </Button>
                    )}
                </div>

                {occupants.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Users className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No occupants yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first occupant to assign them to a unit.
                            </p>
                            {canCreate && (
                                <Button asChild className="mt-6">
                                    <Link href={createOccupant(property.slug)}>
                                        Add your first occupant
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {occupants.map((occupant) => (
                            <Card key={occupant.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">
                                                {occupant.first_name} {occupant.last_name}
                                            </CardTitle>
                                            <CardDescription className="mt-1 flex flex-col gap-1">
                                                <span className="flex items-center gap-1.5 truncate">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    {occupant.email}
                                                </span>
                                                {occupant.phone && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Phone className="size-3.5 shrink-0" />
                                                        {occupant.phone}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={occupant.status === 'active' ? 'default' : 'secondary'}
                                        >
                                            {occupant.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {occupant.national_id && (
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                                National ID
                                            </p>
                                            <p className="text-sm font-medium">{occupant.national_id}</p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Units
                                        </p>
                                        {occupant.units.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No units assigned
                                            </p>
                                        ) : (
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {occupant.units.map((unit) => (
                                                    <Badge key={unit.id} variant="outline">
                                                        {unit.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    {(canEdit || canDelete) && (
                                        <div className="flex gap-2 pt-1">
                                            {canEdit && (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={editOccupant({ property: property.slug, occupant: occupant.id })}>
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

OccupantsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: '',
        },
        {
            title: 'Occupants',
        },
    ],
};
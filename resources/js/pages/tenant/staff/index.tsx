import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, Phone, Plus, UserCog } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create as createStaff, edit as editStaff } from '@/routes/tenant/staff';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type SubRole = {
    id: number;
    name: string;
    slug: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
};

type StaffMember = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    sub_role: SubRole | null;
    properties: Property[];
};

type Props = {
    organization: Organization;
    staff: StaffMember[];
};

export default function StaffIndex({ organization, staff }: Props) {
    const { tenant } = usePage().props;
    const permissions = tenant?.permissions ?? [];
    const canCreate = permissions.includes('staff.create');
    const canEdit = permissions.includes('staff.edit');
    const canDelete = permissions.includes('staff.delete');

    return (
        <>
            <Head title="Staff" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${organization.name} staff`}
                        description="Manage the people who run your properties."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={createStaff()}>
                                <Plus className="size-4" />
                                Add staff
                            </Link>
                        </Button>
                    )}
                </div>

                {staff.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <UserCog className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No staff yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first staff member to assign properties and roles.
                            </p>
                            {canCreate && (
                                <Button asChild className="mt-6">
                                    <Link href={createStaff()}>Add your first staff member</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {staff.map((member) => (
                            <Card key={member.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">
                                                {member.name}
                                            </CardTitle>
                                            <CardDescription className="mt-1 flex flex-col gap-1">
                                                <span className="flex items-center gap-1.5 truncate">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    {member.email}
                                                </span>
                                                {member.phone && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Phone className="size-3.5 shrink-0" />
                                                        {member.phone}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={member.status === 'active' ? 'default' : 'secondary'}
                                        >
                                            {member.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Role
                                        </p>
                                        <p className="text-sm font-medium">
                                            {member.sub_role?.name ?? 'No role assigned'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Manages
                                        </p>
                                        {member.properties.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No properties assigned
                                            </p>
                                        ) : (
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {member.properties.map((property) => (
                                                    <Badge key={property.id} variant="outline">
                                                        {property.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    {(canEdit || canDelete) && (
                                        <div className="flex gap-2 pt-1">
                                            {canEdit && (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={editStaff(member.id)}>
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

StaffIndex.layout = {
    breadcrumbs: [
        {
            title: 'Staff',
        },
    ],
};
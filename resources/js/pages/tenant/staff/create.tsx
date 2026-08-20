import { Form, Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as staffIndex, store } from '@/routes/tenant/staff';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type SubRole = {
    id: number;
    name: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Props = {
    organization: Organization;
    sub_roles: SubRole[];
    properties: Property[];
};

export default function StaffCreate({ organization, sub_roles, properties }: Props) {
    const [subRoleId, setSubRoleId] = useState('');
    const [propertyIds, setPropertyIds] = useState<number[]>([]);

    const toggleProperty = (id: number) => {
        setPropertyIds((prev) =>
            prev.includes(id) ? prev.filter((pid) => pid !== id) : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Add staff" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Add a staff member"
                        description={`Give ${organization.name} someone to manage its properties.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={staffIndex()}>Back to staff</Link>
                    </Button>
                </div>

                <Form {...store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 rounded-xl border border-input p-6 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        autoComplete="off"
                                        placeholder="Jane Wanjiru"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        placeholder="jane@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone (optional)</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        autoComplete="off"
                                        placeholder="+254 700 000 000"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sub_role_id">Role</Label>
                                    <input type="hidden" name="sub_role_id" value={subRoleId} />
                                    <Select value={subRoleId} onValueChange={setSubRoleId}>
                                        <SelectTrigger id="sub_role_id" className="w-full">
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sub_roles.map((role) => (
                                                <SelectItem key={role.id} value={String(role.id)}>
                                                    {role.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.sub_role_id} />
                                </div>
                            </div>

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Properties to manage"
                                    description="Select the properties this staff member can manage. They will land directly on a single assigned property after login."
                                />
                                {properties.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No properties yet. Create a property before adding staff.
                                    </p>
                                ) : (
                                    <div className="grid gap-3">
                                        {properties.map((property) => (
                                            <label
                                                key={property.id}
                                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={propertyIds.includes(property.id)}
                                                    onCheckedChange={() =>
                                                        toggleProperty(property.id)
                                                    }
                                                />
                                                <input
                                                    type="hidden"
                                                    name="property_ids[]"
                                                    value={String(property.id)}
                                                    disabled={!propertyIds.includes(property.id)}
                                                />
                                                <Building2 className="size-4 text-muted-foreground" />
                                                <span className="font-medium">{property.name}</span>
                                            </label>
                                        ))}
                                        <InputError message={errors.property_ids} />
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add staff member'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={staffIndex()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

StaffCreate.layout = {
    breadcrumbs: [
        {
            title: 'Staff',
            href: staffIndex(),
        },
        {
            title: 'Add staff',
        },
    ],
};
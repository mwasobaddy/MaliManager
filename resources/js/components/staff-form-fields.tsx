import { Building2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

type SubRole = {
    id: number;
    name: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Defaults = {
    name?: string;
    email?: string;
    phone?: string | null;
    sub_role_id?: number | null;
    property_ids?: number[];
};

type Props = {
    sub_roles: SubRole[];
    properties: Property[];
    errors: Record<string, string>;
    idPrefix: string;
    propertiesHint?: string;
    defaults?: Defaults;
};

/**
 * The staff identity + delegation fields shared by the create and edit
 * sheets. Mostly uncontrolled over an Inertia Form; the sheet owns the two
 * interactive bits (role select, property toggles) as local state so the
 * posted form data always matches what is on screen. Callers must `key`
 * the sheet content by record so switching rows resets the state.
 */
export default function StaffFormFields({
    sub_roles,
    properties,
    errors,
    idPrefix,
    propertiesHint = 'Select the properties this staff member can manage.',
    defaults = {},
}: Props) {
    const [subRoleId, setSubRoleId] = useState(
        defaults.sub_role_id ? String(defaults.sub_role_id) : '',
    );

    return (
        <>
            <div className="grid gap-6 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-name`}>Full name</Label>
                    <Input
                        id={`${idPrefix}-name`}
                        name="name"
                        type="text"
                        required
                        autoComplete="off"
                        placeholder="Jane Wanjiru"
                        defaultValue={defaults.name ?? ''}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-email`}>Email address</Label>
                    <Input
                        id={`${idPrefix}-email`}
                        name="email"
                        type="email"
                        required
                        autoComplete="off"
                        placeholder="jane@example.com"
                        defaultValue={defaults.email ?? ''}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-phone`}>
                        Phone (optional)
                    </Label>
                    <Input
                        id={`${idPrefix}-phone`}
                        name="phone"
                        type="tel"
                        autoComplete="off"
                        placeholder="+254 700 000 000"
                        defaultValue={defaults.phone ?? ''}
                    />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-sub_role_id`}>Role</Label>
                    <input type="hidden" name="sub_role_id" value={subRoleId} />
                    <Select value={subRoleId} onValueChange={setSubRoleId}>
                        <SelectTrigger
                            id={`${idPrefix}-sub_role_id`}
                            className="w-full"
                        >
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            {sub_roles.map((role) => (
                                <SelectItem
                                    key={role.id}
                                    value={String(role.id)}
                                >
                                    {role.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.sub_role_id} />
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Properties to manage"
                    description={propertiesHint}
                />
                {properties.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No properties yet. Create a property before adding
                        staff.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        {properties.map((property) => (
                            <StaffPropertyToggle
                                key={property.id}
                                property={property}
                                defaultChecked={(
                                    defaults.property_ids ?? []
                                ).includes(property.id)}
                            />
                        ))}
                        <InputError message={errors.property_ids} />
                    </div>
                )}
            </div>
        </>
    );
}

function StaffPropertyToggle({
    property,
    defaultChecked,
}: {
    property: Property;
    defaultChecked: boolean;
}) {
    const [checked, setChecked] = useState(defaultChecked);

    return (
        <label className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted">
            <Checkbox
                checked={checked}
                onCheckedChange={() => setChecked((prev) => !prev)}
            />
            <input
                type="hidden"
                name="property_ids[]"
                value={String(property.id)}
                disabled={!checked}
            />
            <Building2 className="size-4 text-muted-foreground" />
            <span className="font-medium">{property.name}</span>
        </label>
    );
}

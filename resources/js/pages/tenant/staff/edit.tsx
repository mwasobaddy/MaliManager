import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Building2, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as destroyStaff,
    index as staffIndex,
    update,
} from '@/routes/tenant/staff';

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

type StaffMember = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    sub_role_id: number | null;
    property_ids: number[];
};

type Props = {
    organization: Organization;
    staff: StaffMember;
    sub_roles: SubRole[];
    properties: Property[];
};

export default function StaffEdit({
    organization,
    staff,
    sub_roles,
    properties,
}: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canDelete = permissions.includes('staff.delete');
    const passwordInput = useRef<HTMLInputElement>(null);
    const [subRoleId, setSubRoleId] = useState(
        staff.sub_role_id ? String(staff.sub_role_id) : '',
    );
    const [propertyIds, setPropertyIds] = useState<number[]>(
        staff.property_ids,
    );

    const toggleProperty = (id: number) => {
        setPropertyIds((prev) =>
            prev.includes(id)
                ? prev.filter((pid) => pid !== id)
                : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Edit staff" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Heading
                            variant="small"
                            title={`Edit ${staff.name}`}
                            description={`Update ${organization.name} staff member details and assignments.`}
                        />
                    </div>
                    <Button asChild variant="outline">
                        <Link href={staffIndex()}>Back to staff</Link>
                    </Button>
                </div>

                <Form {...update.form(staff.id)} className="space-y-6">
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
                                        defaultValue={staff.name}
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
                                        defaultValue={staff.email}
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        Phone (optional)
                                    </Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        autoComplete="off"
                                        defaultValue={staff.phone ?? ''}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sub_role_id">Role</Label>
                                    <input
                                        type="hidden"
                                        name="sub_role_id"
                                        value={subRoleId}
                                    />
                                    <Select
                                        value={subRoleId}
                                        onValueChange={setSubRoleId}
                                    >
                                        <SelectTrigger
                                            id="sub_role_id"
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

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Properties to manage"
                                    description="Select the properties this staff member can manage."
                                />
                                {properties.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No properties yet. Create a property
                                        before assigning staff.
                                    </p>
                                ) : (
                                    <div className="grid gap-3">
                                        {properties.map((property) => (
                                            <label
                                                key={property.id}
                                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={propertyIds.includes(
                                                        property.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleProperty(
                                                            property.id,
                                                        )
                                                    }
                                                />
                                                <input
                                                    type="hidden"
                                                    name="property_ids[]"
                                                    value={String(property.id)}
                                                    disabled={
                                                        !propertyIds.includes(
                                                            property.id,
                                                        )
                                                    }
                                                />
                                                <Building2 className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {property.name}
                                                </span>
                                            </label>
                                        ))}
                                        <InputError
                                            message={errors.property_ids}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save changes'}
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={staffIndex()}>Cancel</Link>
                                    </Button>
                                </div>

                                {canDelete && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="destructive"
                                                type="button"
                                            >
                                                <Trash2 className="size-4" />
                                                Remove staff
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Remove {staff.name} from{' '}
                                                {organization.name}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This will soft-delete their
                                                account and revoke access to
                                                managed properties. Please enter
                                                your password to confirm.
                                            </DialogDescription>

                                            <Form
                                                {...destroyStaff.form(staff.id)}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                onError={() =>
                                                    passwordInput.current?.focus()
                                                }
                                                resetOnSuccess
                                                className="space-y-6"
                                            >
                                                {({
                                                    resetAndClearErrors,
                                                    processing,
                                                    errors,
                                                }) => (
                                                    <>
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor="password"
                                                                className="sr-only"
                                                            >
                                                                Password
                                                            </Label>

                                                            <PasswordInput
                                                                id="password"
                                                                name="password"
                                                                ref={
                                                                    passwordInput
                                                                }
                                                                placeholder="Password"
                                                                autoComplete="current-password"
                                                            />

                                                            <InputError
                                                                message={
                                                                    errors.password
                                                                }
                                                            />
                                                        </div>

                                                        <DialogFooter className="gap-2">
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="secondary"
                                                                    onClick={() =>
                                                                        resetAndClearErrors()
                                                                    }
                                                                >
                                                                    Cancel
                                                                </Button>
                                                            </DialogClose>

                                                            <Button
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                                asChild
                                                            >
                                                                <button
                                                                    type="submit"
                                                                    data-test="confirm-remove-staff-button"
                                                                >
                                                                    Remove staff
                                                                </button>
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

StaffEdit.layout = {
    breadcrumbs: [
        {
            title: 'Staff',
            href: staffIndex(),
        },
        {
            title: 'Edit staff',
        },
    ],
};

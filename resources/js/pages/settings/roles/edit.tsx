import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as rolesIndex, update as updateRole } from '@/routes/settings/roles';

type Permission = {
    value: string;
    label: string;
    assigned: boolean;
};

type Props = {
    role: { id: number; name: string };
    permissions: Permission[];
};

export default function RoleEdit({ role, permissions }: Props) {
    const form = useForm<{ permissions: string[] }>({
        permissions: permissions.filter((p) => p.assigned).map((p) => p.value),
    });

    const toggle = (value: string) => {
        const updated = form.data.permissions.includes(value)
            ? form.data.permissions.filter((p) => p !== value)
            : [...form.data.permissions, value];

        form.setData('permissions', updated);
    };

    const submit = () => {
        form.patch(updateRole({ role: role.id }).url);
    };

    return (
        <>
            <Head title={`Edit ${role.name} permissions`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title={`Permissions for ${role.name}`}
                    description="Assign or revoke the platform-wide permissions granted to this role."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Central permissions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {permissions.map((permission) => (
                            <div key={permission.value} className="flex items-center gap-3">
                                <Checkbox
                                    id={permission.value}
                                    checked={form.data.permissions.includes(permission.value)}
                                    onCheckedChange={() => toggle(permission.value)}
                                />
                                <Label htmlFor={permission.value} className="cursor-pointer">
                                    {permission.label}
                                </Label>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <a href={rolesIndex().url}>Back to roles</a>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        Save
                    </Button>
                </div>
            </div>
        </>
    );
}

RoleEdit.layout = {
    breadcrumbs: [
        { title: 'Settings' },
        { title: 'Roles', href: rolesIndex().url },
        { title: 'Edit role' },
    ],
};

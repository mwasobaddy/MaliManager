import { Head, useForm } from '@inertiajs/react';
import { Edit, Shield, UsersRound } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit as editRole } from '@/routes/platform/roles';
import { role as assignRoleRoute } from '@/routes/platform/users';

type PermissionCatalog = { value: string; label: string }[];
type Role = { id: number; name: string; permissions: string[] };
type User = { id: number; name: string; email: string; roles: string[] };

type Props = {
    roles: Role[];
    permissions: PermissionCatalog;
    users: User[];
    isAdmin: boolean;
};

const ROLE_OPTIONS = [
    'admin',
    'organization-owner',
    'searcher',
    'occupant',
] as const;

export default function RolesIndex({ roles, permissions, users }: Props) {
    const roleForm = useForm<{ role: string }>({ role: '' });

    const assignRole = (user: User, roleName: string) => {
        roleForm.setData('role', roleName);
        roleForm.patch(assignRoleRoute({ user: user.id }).url, {
            preserveScroll: true,
            onSuccess: () => roleForm.reset('role'),
        });
    };

    const label = (value: string) =>
        permissions.find((p) => p.value === value)?.label ?? value;

    return (
        <>
            <Head title="Roles" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Roles"
                        description="Platform-wide roles and the central permissions assigned to them."
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="size-5" /> Platform roles
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-2">Role</th>
                                    <th className="px-3 py-2">
                                        Central permissions
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {roles.map((role) => (
                                    <tr
                                        key={role.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {role.name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {role.permissions.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    None
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {role.permissions.map(
                                                        (p) => (
                                                            <Badge key={p}>
                                                                {label(p)}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <a
                                                    href={
                                                        editRole({
                                                            role: role.id,
                                                        }).url
                                                    }
                                                >
                                                    <Edit className="size-4" />
                                                    Edit
                                                </a>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UsersRound className="size-5" /> Users
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-2">User</th>
                                    <th className="px-3 py-2">Current role</th>
                                    <th className="px-3 py-2 text-right">
                                        Assign
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {user.roles.join(', ') || '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <select
                                                className="w-full rounded-md border border-input bg-background px-2 py-1 text-sm"
                                                defaultValue=""
                                                onChange={(e) =>
                                                    assignRole(
                                                        user,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="" disabled>
                                                    Pick a role…
                                                </option>
                                                {ROLE_OPTIONS.map((r) => (
                                                    <option key={r} value={r}>
                                                        {r}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Settings' }, { title: 'Roles' }],
};

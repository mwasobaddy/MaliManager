import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Download, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import * as usersRoutes from '@/routes/users';

type PersonRow = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    national_id: string | null;
    gender: string | null;
    status: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    roles: { id: number; name: string }[];
    person?: PersonRow | null;
    created_at?: string;
};

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search: string; status: string; role: string };
    platformRoles: { value: string; label: string }[];
};

const statusStyles: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    inactive:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
};

export default function UsersIndex({ users, filters, platformRoles }: Props) {
    const page = usePage();
    const permissions =
        ((page.props.auth?.permissions ?? []) as string[]) ?? [];
    const canCreate = permissions.includes('user.create');
    const canEdit = permissions.includes('user.edit');
    const canDelete = permissions.includes('user.delete');
    const canExport = permissions.includes('user.export');

    const form = useForm({
        search: filters.search,
        status: filters.status,
        role: filters.role,
    });

    const applyFilters = () => {
        form.get(usersRoutes.index().url, {
            preserveState: true,
            replace: true,
        });
    };

    const [suspendTarget, setSuspendTarget] = useState<UserRow | null>(null);
    const [suspending, setSuspending] = useState(false);

    const confirmToggleStatus = () => {
        if (!suspendTarget) {
            return;
        }

        setSuspending(true);
        router.patch(
            usersRoutes.status(suspendTarget.id),
            {
                status:
                    suspendTarget.status === 'active' ? 'suspended' : 'active',
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSuspending(false);
                    setSuspendTarget(null);
                },
            },
        );
    };

    const [deleteTarget, setDeleteTarget] = useState<UserRow | null>(null);

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="User management"
                        description="Create and manage people and their user accounts."
                    />
                    <div className="flex gap-2">
                        {canExport && (
                            <Button variant="outline" asChild>
                                <a href={usersRoutes.exportMethod().url}>
                                    <Download className="mr-2 size-4" />
                                    Export
                                </a>
                            </Button>
                        )}
                        {canCreate && (
                            <Button asChild>
                                <Link href={usersRoutes.create()}>
                                    <Plus className="mr-2 size-4" />
                                    New user
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters();
                            }}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <div className="relative min-w-56 flex-1">
                                <Search className="absolute top-2.5 left-2 size-4 text-muted-foreground" />
                                <input
                                    value={form.data.search}
                                    onChange={(event) =>
                                        form.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Search name, email, phone..."
                                    className="w-full rounded-md border bg-background py-2 pr-3 pl-8 text-sm"
                                />
                            </div>
                            <select
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Any status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <select
                                value={form.data.role}
                                onChange={(event) =>
                                    form.setData('role', event.target.value)
                                }
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Any role</option>
                                {platformRoles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            <Button type="submit" variant="secondary">
                                Filter
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Email
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Phone
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Roles
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No users found.
                                            </td>
                                        </tr>
                                    )}
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="border-b">
                                            <td className="px-3 py-2 font-medium">
                                                {user.name}
                                            </td>
                                            <td className="px-3 py-2">
                                                {user.email}
                                            </td>
                                            <td className="px-3 py-2">
                                                {user.phone ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.length ===
                                                        0 && (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                    {user.roles.map((role) => (
                                                        <Badge
                                                            key={role.id}
                                                            variant="secondary"
                                                        >
                                                            {role.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusStyles[user.status] ?? ''}`}
                                                >
                                                    {user.status}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-1">
                                                    {canEdit && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={usersRoutes.edit(
                                                                    user.id,
                                                                )}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canEdit && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setSuspendTarget(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            {user.status ===
                                                            'active'
                                                                ? 'Suspend'
                                                                : 'Activate'}
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    user,
                                                                )
                                                            }
                                                            className="text-red-600"
                                                        >
                                                            Delete
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {users.current_page} of {users.last_page} (
                            {users.total} users)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={users.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        usersRoutes.index().url,
                                        {
                                            ...form.data,
                                            page: users.current_page - 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={users.current_page >= users.last_page}
                                onClick={() =>
                                    router.get(
                                        usersRoutes.index().url,
                                        {
                                            ...form.data,
                                            page: users.current_page + 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <PasswordConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                formAction={usersRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.email ?? ''}?`}
                description="This soft-deletes the user account. Please enter your password to confirm."
            />

            <Dialog
                open={suspendTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSuspendTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {suspendTarget?.status === 'active'
                                ? 'Suspend user?'
                                : 'Activate user?'}
                        </DialogTitle>
                        <DialogDescription>
                            {suspendTarget?.status === 'active'
                                ? `Suspend ${suspendTarget?.email ?? ''}? They will be signed out of every device and unable to access the platform until reactivated.`
                                : `Reactivate ${suspendTarget?.email ?? ''}? They will be able to sign in again.`}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
                                onClick={() => setSuspendTarget(null)}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant={
                                suspendTarget?.status === 'active'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={suspending}
                            onClick={confirmToggleStatus}
                        >
                            {suspendTarget?.status === 'active'
                                ? 'Suspend'
                                : 'Activate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
    ],
};

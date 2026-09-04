import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Building2, Download, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import * as orgRoutes from '@/routes/organizations';

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    status: string;
    plan: string | null;
    domain: string | null;
    users_count: number;
    created_at: string | null;
};

type Props = {
    organizations: {
        data: OrganizationRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search: string; status: string };
};

const statusStyles: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
};

export default function OrganizationsIndex({ organizations, filters }: Props) {
    const page = usePage();
    const permissions =
        ((page.props.auth?.permissions ?? []) as string[]) ?? [];
    const canCreate = permissions.includes('organization.create');
    const canEdit = permissions.includes('organization.edit');
    const canDelete = permissions.includes('organization.delete');
    const canExport = permissions.includes('organization.export');

    const form = useForm({
        search: filters.search,
        status: filters.status,
    });

    const [deleteTarget, setDeleteTarget] = useState<OrganizationRow | null>(
        null,
    );

    return (
        <>
            <Head title="Organizations" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Organization management"
                        description="Create and manage organizations, their plans and tenancy."
                    />
                    <div className="flex gap-2">
                        {canExport && (
                            <Button variant="outline" asChild>
                                <a href={orgRoutes.exportMethod().url}>
                                    <Download className="mr-2 size-4" />
                                    Export
                                </a>
                            </Button>
                        )}
                        {canCreate && (
                            <Button asChild>
                                <Link href={orgRoutes.create()}>
                                    <Plus className="mr-2 size-4" />
                                    New organization
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
                                form.get(orgRoutes.index().url, {
                                    preserveState: true,
                                    replace: true,
                                });
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
                                    placeholder="Search name, slug, email..."
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
                                <option value="suspended">Suspended</option>
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
                                            Organization
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Plan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Domain
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Members
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
                                    {organizations.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No organizations found.
                                            </td>
                                        </tr>
                                    )}
                                    {organizations.data.map((organization) => (
                                        <tr
                                            key={organization.id}
                                            className="border-b"
                                        >
                                            <td className="px-3 py-2">
                                                <div className="flex items-center gap-2 font-medium">
                                                    <Building2 className="size-4 text-muted-foreground" />
                                                    {organization.name}
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {organization.slug}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2">
                                                {organization.plan ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-xs">
                                                {organization.domain ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {organization.users_count}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusStyles[organization.status] ?? ''}`}
                                                >
                                                    {organization.status}
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
                                                                href={orgRoutes.edit(
                                                                    organization.id,
                                                                )}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    organization,
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

                {organizations.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {organizations.current_page} of{' '}
                            {organizations.last_page} ({organizations.total}{' '}
                            organizations)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={organizations.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        orgRoutes.index().url,
                                        {
                                            ...form.data,
                                            page:
                                                organizations.current_page - 1,
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
                                disabled={
                                    organizations.current_page >=
                                    organizations.last_page
                                }
                                onClick={() =>
                                    router.get(
                                        orgRoutes.index().url,
                                        {
                                            ...form.data,
                                            page:
                                                organizations.current_page + 1,
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
                formAction={orgRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.name ?? ''}?`}
                description="This soft-deletes the organization and its tenant will no longer be reachable. Please enter your password to confirm."
            />
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Organizations', href: '/organizations' },
    ],
};

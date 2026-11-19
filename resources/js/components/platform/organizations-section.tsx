import { Form, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { impersonate } from '@/routes/platform';
import * as orgRoutes from '@/routes/platform/organizations';
import type { OrganizationRow, OrganizationsPagination } from './types';

export function OrganizationsSection({
    organizations,
    pagination,
    onOrgPage,
}: {
    organizations: OrganizationRow[];
    pagination: OrganizationsPagination | null;
    onOrgPage: (page: number) => void;
}) {
    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold">All organizations</h2>
                <Link
                    href={orgRoutes.index().url}
                    className="text-sm font-medium text-accent-text hover:text-foreground"
                >
                    Manage all
                </Link>
            </div>

            {organizations.length === 0 ? (
                <Card>
                    <CardContent className="py-10 text-center text-sm text-muted-foreground">
                        No organizations have been created yet.
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4">
                    {organizations.map((organization) => (
                        <Card key={organization.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-4 text-muted-foreground" />
                                        <CardTitle>
                                            {organization.name}
                                        </CardTitle>
                                        <Badge variant="primary">
                                            {organization.plan ?? 'No plan'}
                                        </Badge>
                                        <Badge
                                            variant={
                                                organization.status === 'active'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {organization.status}
                                        </Badge>
                                    </div>
                                    <CardDescription>
                                        {organization.slug}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {organization.users.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No members.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border rounded-lg border">
                                        {organization.users.map((user) => (
                                            <div
                                                key={user.id}
                                                className="flex items-center justify-between gap-4 px-4 py-3"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {user.name}
                                                    </p>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {user.email}
                                                    </p>
                                                </div>
                                                <Form
                                                    method="post"
                                                    action={impersonate([
                                                        organization.id,
                                                        user.id,
                                                    ])}
                                                    as="button"
                                                    disabled={
                                                        !organization.domain
                                                    }
                                                    title={
                                                        organization.domain
                                                            ? `Log in as ${user.name}`
                                                            : 'No domain configured'
                                                    }
                                                >
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            !organization.domain
                                                        }
                                                    >
                                                        Impersonate
                                                    </Button>
                                                </Form>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {pagination !== null && pagination.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground tabular-nums">
                        Page {pagination.current_page} of {pagination.last_page}{' '}
                        · {pagination.total} total
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={pagination.current_page <= 1}
                            onClick={() =>
                                onOrgPage(pagination.current_page - 1)
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={
                                pagination.current_page >= pagination.last_page
                            }
                            onClick={() =>
                                onOrgPage(pagination.current_page + 1)
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}

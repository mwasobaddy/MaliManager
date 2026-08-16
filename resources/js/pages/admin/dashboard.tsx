import { Form, Head } from '@inertiajs/react';
import { Building2, Shield } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { impersonate } from '@/routes/admin';

type Organization = {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    domain: string | null;
    users: Array<{ id: number; name: string; email: string }>;
    created_at: string | null;
};

type Props = {
    organizations: Organization[];
};

export default function AdminDashboard({ organizations }: Props) {
    return (
        <>
            <Head title="Admin" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-sidebar-primary/10">
                        <Shield className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold">Platform Admin</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage organizations and impersonate their users to diagnose issues.
                        </p>
                    </div>
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
                                            <CardTitle>{organization.name}</CardTitle>
                                            <Badge variant="secondary">{organization.plan ?? 'No plan'}</Badge>
                                            <Badge
                                                variant={organization.status === 'active' ? 'default' : 'secondary'}
                                            >
                                                {organization.status}
                                            </Badge>
                                        </div>
                                        <CardDescription>{organization.slug}</CardDescription>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {organization.users.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">No members.</p>
                                    ) : (
                                        <div className="divide-y divide-border rounded-lg border">
                                            {organization.users.map((user) => (
                                                <div
                                                    key={user.id}
                                                    className="flex items-center justify-between gap-4 px-4 py-3"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">{user.name}</p>
                                                        <p className="truncate text-sm text-muted-foreground">
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                    <Form
                                                        method="post"
                                                        action={impersonate([organization.id, user.id])}
                                                        as="button"
                                                        disabled={!organization.domain}
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
                                                            disabled={!organization.domain}
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
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Admin',
        },
    ],
};
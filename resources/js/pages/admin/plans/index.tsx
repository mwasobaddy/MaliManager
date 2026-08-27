import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import * as planRoutes from '@/routes/plans';

type PlanRow = {
    id: number;
    name: string;
    slug: string;
    currency: string;
    price: number;
    properties_limit: number | null;
    units_limit: number | null;
    features: string[] | null;
    is_active: boolean;
    organizations_count: number;
};

type Props = {
    plans: {
        data: PlanRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
    };
};

export default function PlansIndex({ plans, filters }: Props) {
    const authPermissions = (usePage().props.auth?.permissions ?? []) as string[];
    const canCreatePlans = authPermissions.includes('plan.create');
    const canDeletePlans = authPermissions.includes('plan.delete');
    const [deleteTarget, setDeleteTarget] = useState<PlanRow | null>(null);

    const form = useForm({ search: filters.search });

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(planRoutes.index().url, { search: form.data.search }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Plans" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading title="Plans" description="Subscription tiers and their limits." />
                    {canCreatePlans && (
                        <Button asChild>
                            <Link href={planRoutes.create()}>
                                <Plus className="size-4" />
                                New plan
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="p-3">
                        <Form onSubmit={submitSearch} className="flex gap-2">
                            {({ processing }) => (
                                <>
                                    <div className="relative flex-1">
                                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            name="search"
                                            value={form.data.search}
                                            onChange={(e) => form.setData('search', e.target.value)}
                                            placeholder="Search plans"
                                            className="pl-8"
                                        />
                                    </div>
                                    <Button type="submit" variant="secondary" disabled={processing}>
                                        Search
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">Plan</th>
                                        <th className="px-3 py-2 font-medium">Price</th>
                                        <th className="px-3 py-2 font-medium">Limits</th>
                                        <th className="px-3 py-2 font-medium">Features</th>
                                        <th className="px-3 py-2 font-medium">Organizations</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {plans.data.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="py-8 text-center text-muted-foreground">
                                                No plans found.
                                            </td>
                                        </tr>
                                    )}
                                    {plans.data.map((plan) => (
                                        <tr key={plan.id} className="border-b">
                                            <td className="px-3 py-2">
                                                <div className="font-medium">{plan.name}</div>
                                                <span className="text-xs text-muted-foreground">{plan.slug}</span>
                                            </td>
                                            <td className="px-3 py-2">
                                                {plan.currency} {plan.price.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-2 text-xs">
                                                {plan.properties_limit ?? '∞'} properties
                                                <br />
                                                {plan.units_limit ?? '∞'} units
                                            </td>
                                            <td className="px-3 py-2 text-xs">
                                                {plan.features?.length ? `${plan.features.length} feature(s)` : '—'}
                                            </td>
                                            <td className="px-3 py-2">{plan.organizations_count}</td>
                                            <td className="px-3 py-2">
                                                <Badge variant={plan.is_active ? 'default' : 'secondary'}>
                                                    {plan.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={planRoutes.edit(plan.id)}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    {canDeletePlans && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setDeleteTarget(plan)}
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="size-4" />
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

                {plans.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {plans.current_page} of {plans.last_page} ({plans.total} plans)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={plans.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        planRoutes.index().url,
                                        { search: filters.search, page: plans.current_page - 1 },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={plans.current_page >= plans.last_page}
                                onClick={() =>
                                    router.get(
                                        planRoutes.index().url,
                                        { search: filters.search, page: plans.current_page + 1 },
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
                formAction={planRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.name ?? ''}?`}
                description="This soft-deletes the plan. Organizations already on it are unaffected. Please enter your password to confirm."
            />
        </>
    );
}

PlansIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Plans', href: planRoutes.index().url },
    ],
};

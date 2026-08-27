import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import * as planRoutes from '@/routes/plans';

type PlanData = {
    name: string;
    slug: string;
    description: string;
    currency: string;
    price: string;
    properties_limit: string;
    units_limit: string;
    has_dedicated_db: boolean;
    has_custom_domain: boolean;
    has_email_notifications: boolean;
    has_sms_notifications: boolean;
    features: string;
    is_active: boolean;
    sort_order: string;
};

type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    currency: string;
    price: number;
    properties_limit: number | null;
    units_limit: number | null;
    has_dedicated_db: boolean;
    has_custom_domain: boolean;
    has_email_notifications: boolean;
    has_sms_notifications: boolean;
    features: string[] | null;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    plan?: Plan;
};

export default function PlanForm({ plan }: Props) {
    const isEdit = !!plan;

    const form = useForm<PlanData>({
        name: plan?.name ?? '',
        slug: plan?.slug ?? '',
        description: plan?.description ?? '',
        currency: plan?.currency ?? 'KES',
        price: String(plan?.price ?? 0),
        properties_limit: plan?.properties_limit != null ? String(plan.properties_limit) : '',
        units_limit: plan?.units_limit != null ? String(plan.units_limit) : '',
        has_dedicated_db: plan?.has_dedicated_db ?? false,
        has_custom_domain: plan?.has_custom_domain ?? false,
        has_email_notifications: plan?.has_email_notifications ?? false,
        has_sms_notifications: plan?.has_sms_notifications ?? false,
        features: plan?.features?.join('\n') ?? '',
        is_active: plan?.is_active ?? true,
        sort_order: String(plan?.sort_order ?? 0),
    });

    form.transform((data) => ({
        ...data,
        price: Number(data.price),
        properties_limit: data.properties_limit === '' ? null : Number(data.properties_limit),
        units_limit: data.units_limit === '' ? null : Number(data.units_limit),
        sort_order: Number(data.sort_order) || 0,
        features: data.features
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
    }));

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit && plan) {
            form.patch(planRoutes.update(plan.id).url);
        } else {
            form.post(planRoutes.store().url);
        }
    }

    return (
        <>
            <Head title={isEdit ? `Edit ${plan?.name ?? 'plan'}` : 'New plan'} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={planRoutes.index().url}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={isEdit ? `Edit ${plan?.name ?? 'plan'}` : 'New plan'}
                        description="Define pricing, limits and enabled features for this tier."
                    />
                </div>

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-2">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input id="slug" name="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} required />
                                <InputError message={form.errors.slug} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">Sort order</Label>
                                <Input id="sort_order" name="sort_order" type="number" min={0} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} />
                                <InputError message={form.errors.sort_order} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pricing &amp; limits</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input id="currency" name="currency" maxLength={3} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value.toUpperCase())} required />
                                    <InputError message={form.errors.currency} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="price">Price (per period)</Label>
                                    <Input id="price" name="price" type="number" min={0} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} required />
                                    <InputError message={form.errors.price} />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="properties_limit">Properties limit</Label>
                                    <Input id="properties_limit" name="properties_limit" type="number" min={0} value={form.data.properties_limit} onChange={(e) => form.setData('properties_limit', e.target.value)} placeholder="Unlimited" />
                                    <InputError message={form.errors.properties_limit} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="units_limit">Units limit</Label>
                                    <Input id="units_limit" name="units_limit" type="number" min={0} value={form.data.units_limit} onChange={(e) => form.setData('units_limit', e.target.value)} placeholder="Unlimited" />
                                    <InputError message={form.errors.units_limit} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Features &amp; flags</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="features">Features (one per line)</Label>
                                <textarea
                                    id="features"
                                    name="features"
                                    rows={4}
                                    value={form.data.features}
                                    onChange={(e) => form.setData('features', e.target.value)}
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    placeholder={'SMS alerts\nPriority support'}
                                />
                                <InputError message={form.errors.features} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', !!v)} />
                                Active (available for new organizations)
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.has_dedicated_db} onCheckedChange={(v) => form.setData('has_dedicated_db', !!v)} />
                                Dedicated database
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.has_custom_domain} onCheckedChange={(v) => form.setData('has_custom_domain', !!v)} />
                                Custom domain
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.has_email_notifications} onCheckedChange={(v) => form.setData('has_email_notifications', !!v)} />
                                Email notifications
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.has_sms_notifications} onCheckedChange={(v) => form.setData('has_sms_notifications', !!v)} />
                                SMS notifications
                            </label>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2 lg:col-span-2">
                        <Button variant="outline" asChild>
                            <Link href={planRoutes.index().url}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Save changes' : 'Create plan'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

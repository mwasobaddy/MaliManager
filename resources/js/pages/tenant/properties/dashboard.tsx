import { Form, Head, Link } from '@inertiajs/react';
import { Building2, DoorOpen, Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/tenant/properties';
import { store as storeUnit } from '@/routes/tenant/properties/units';

type Property = {
    id: number;
    name: string;
    slug: string;
    address: string | null;
    city: string | null;
    status: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
    monthly_rent: string | null;
};

type Plan = {
    units_limit: number | null;
};

type Props = {
    property: Property;
    units: Unit[];
    plan: Plan;
};

export default function PropertiesDashboard({ property, units, plan }: Props) {
    const [addUnit, setAddUnit] = useState(false);
    const atUnitLimit =
        plan.units_limit !== null && units.length >= plan.units_limit;

    return (
        <>
            <Head title={property.name} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="space-y-0.5">
                        <Heading
                            variant="small"
                            title={property.name}
                            description={`${property.city ?? 'No city set'}${property.address ? ` · ${property.address}` : ''}`}
                        />
                        <Badge variant={property.status === 'active' ? 'default' : 'secondary'}>
                            {property.status}
                        </Badge>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={index()}>All properties</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-2">
                                <DoorOpen className="size-4 text-muted-foreground" />
                                <CardTitle>Units</CardTitle>
                                <CardDescription>
                                    {units.length} total
                                </CardDescription>
                            </div>
                            {!addUnit && (
                                <Button
                                    size="sm"
                                    onClick={() => setAddUnit(true)}
                                    disabled={atUnitLimit}
                                    title={atUnitLimit ? 'Your plan allows no more units' : undefined}
                                >
                                    <Plus className="size-4" />
                                    Add unit
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {atUnitLimit && (
                            <p className="mb-4 text-sm text-destructive">
                                Your current plan allows up to {plan.units_limit} units. Please
                                upgrade your plan to add more.
                            </p>
                        )}

                        {addUnit && (
                            <Form
                                method="post"
                                action={storeUnit(property.slug)}
                                options={{ preserveScroll: true }}
                                className="mb-6 grid gap-4 rounded-lg border border-input p-4 md:grid-cols-[1fr_1fr_1fr_auto]"
                                onSuccess={() => setAddUnit(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-name">Unit name</Label>
                                            <Input
                                                id="unit-name"
                                                name="name"
                                                type="text"
                                                required
                                                placeholder="A1"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-type">Type</Label>
                                            <Input
                                                id="unit-type"
                                                name="type"
                                                type="text"
                                                placeholder="1 Bedroom"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-rent">Monthly rent</Label>
                                            <Input
                                                id="unit-rent"
                                                name="monthly_rent"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="25000"
                                            />
                                            <InputError message={errors.monthly_rent} />
                                        </div>
                                        <div className="flex items-end gap-2">
                                            <Button type="submit" disabled={processing}>
                                                {processing ? 'Adding…' : 'Add'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() => setAddUnit(false)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                        <InputError message={errors.plan} />
                                    </>
                                )}
                            </Form>
                        )}

                        {units.length === 0 ? (
                            <div className="py-10 text-center text-sm text-muted-foreground">
                                <Building2 className="mx-auto size-8" />
                                <p className="mt-3">No units yet. Add the first unit to this property.</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border rounded-lg border">
                                {units.map((unit) => (
                                    <div
                                        key={unit.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {unit.name}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {unit.type ?? 'Unit'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {unit.monthly_rent !== null && (
                                                <p className="text-sm font-medium">
                                                    {Number(unit.monthly_rent).toLocaleString()}
                                                </p>
                                            )}
                                            <Badge
                                                variant={unit.status === 'vacant' ? 'secondary' : 'default'}
                                            >
                                                {unit.status}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PropertiesDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: index(),
        },
        {
            title: 'Dashboard',
            href: '',
        },
    ],
};
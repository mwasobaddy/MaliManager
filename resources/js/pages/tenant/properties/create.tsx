import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/tenant/properties';
import { store } from '@/routes/tenant/properties';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Plan = {
    properties_limit: number | null;
    units_limit: number | null;
};

type Props = {
    organization: Organization;
    plan: Plan;
    canCreateProperty: boolean;
};

type UnitDraft = {
    name: string;
    type: string;
    monthly_rent: string;
};

const emptyUnit = (): UnitDraft => ({ name: '', type: '', monthly_rent: '' });

export default function PropertiesCreate({
    organization,
    plan,
    canCreateProperty,
}: Props) {
    const [units, setUnits] = useState<UnitDraft[]>([]);

    const updateUnit = (
        index: number,
        field: keyof UnitDraft,
        value: string,
    ) => {
        setUnits((prev) =>
            prev.map((unit, i) =>
                i === index ? { ...unit, [field]: value } : unit,
            ),
        );
    };

    return (
        <>
            <Head title="Add property" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Add a property"
                        description={`Add ${organization.name}'s first property and its units.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={index()}>Back to properties</Link>
                    </Button>
                </div>

                {!canCreateProperty ? (
                    <p className="text-sm text-destructive">
                        Your current plan does not allow adding more properties.
                    </p>
                ) : (
                    <Form {...store.form()} className="space-y-6">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-6 rounded-xl border border-input p-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Property name
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            type="text"
                                            required
                                            autoComplete="off"
                                            placeholder="Sunset Apartments"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            name="city"
                                            type="text"
                                            autoComplete="address-level2"
                                            placeholder="Nairobi"
                                        />
                                        <InputError message={errors.city} />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="address">Address</Label>
                                        <Input
                                            id="address"
                                            name="address"
                                            type="text"
                                            autoComplete="street-address"
                                            placeholder="123 Moi Avenue"
                                        />
                                        <InputError message={errors.address} />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Heading
                                            variant="small"
                                            title="Units"
                                            description={`Optional for now. ${plan.units_limit ? `Your plan allows up to ${plan.units_limit} units per property.` : 'No unit limit on your plan.'}`}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setUnits((prev) => [
                                                    ...prev,
                                                    emptyUnit(),
                                                ])
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Add unit
                                        </Button>
                                    </div>

                                    {units.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            You can add units now or later from
                                            the property page.
                                        </p>
                                    ) : (
                                        <div className="grid gap-4">
                                            {units.map((unit, i) => (
                                                <div
                                                    key={i}
                                                    className="grid gap-4 rounded-xl border border-input p-4 md:grid-cols-[1fr_1fr_1fr_auto]"
                                                >
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`units-${i}-name`}
                                                        >
                                                            Unit name
                                                        </Label>
                                                        <Input
                                                            id={`units-${i}-name`}
                                                            type="text"
                                                            required
                                                            placeholder="A1"
                                                            value={unit.name}
                                                            onChange={(e) =>
                                                                updateUnit(
                                                                    i,
                                                                    'name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name={`units[${i}][name]`}
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`units-${i}-type`}
                                                        >
                                                            Type
                                                        </Label>
                                                        <Input
                                                            id={`units-${i}-type`}
                                                            type="text"
                                                            placeholder="1 Bedroom"
                                                            value={unit.type}
                                                            onChange={(e) =>
                                                                updateUnit(
                                                                    i,
                                                                    'type',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name={`units[${i}][type]`}
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`units-${i}-rent`}
                                                        >
                                                            Monthly rent
                                                        </Label>
                                                        <Input
                                                            id={`units-${i}-rent`}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            placeholder="25000"
                                                            value={
                                                                unit.monthly_rent
                                                            }
                                                            onChange={(e) =>
                                                                updateUnit(
                                                                    i,
                                                                    'monthly_rent',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name={`units[${i}][monthly_rent]`}
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="mt-6 text-destructive"
                                                        onClick={() =>
                                                            setUnits((prev) =>
                                                                prev.filter(
                                                                    (_, j) =>
                                                                        j !== i,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Create property'}
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={index()}>Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

PropertiesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: index(),
        },
        {
            title: 'Add property',
        },
    ],
};

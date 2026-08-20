import { Form, Head, Link } from '@inertiajs/react';
import { DoorOpen } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as occupantsIndex, store } from '@/routes/tenant/occupants';

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
};

type Props = {
    property: Property;
    units: Unit[];
};

export default function OccupantCreate({ property, units }: Props) {
    const [status, setStatus] = useState('active');
    const [unitIds, setUnitIds] = useState<number[]>([]);

    const toggleUnit = (id: number) => {
        setUnitIds((prev) =>
            prev.includes(id) ? prev.filter((uid) => uid !== id) : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Add occupant" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Add an occupant"
                        description={`Assign someone to a unit in ${property.name}.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={occupantsIndex(property.slug)}>Back to occupants</Link>
                    </Button>
                </div>

                <Form {...store.form(property.slug)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 rounded-xl border border-input p-6 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">First name</Label>
                                    <Input
                                        id="first_name"
                                        name="first_name"
                                        type="text"
                                        required
                                        autoComplete="off"
                                        placeholder="Jane"
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last name (optional)</Label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        type="text"
                                        autoComplete="off"
                                        placeholder="Wanjiru"
                                    />
                                    <InputError message={errors.last_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        placeholder="jane@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone (optional)</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        autoComplete="off"
                                        placeholder="+254 700 000 000"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="national_id">National ID (optional)</Label>
                                    <Input
                                        id="national_id"
                                        name="national_id"
                                        type="text"
                                        autoComplete="off"
                                        placeholder="12345678"
                                    />
                                    <InputError message={errors.national_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <input type="hidden" name="status" value={status} />
                                    <Select value={status} onValueChange={setStatus}>
                                        <SelectTrigger id="status" className="w-full">
                                            <SelectValue placeholder="Select a status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                            <SelectItem value="moved_out">Moved out</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Units in this property"
                                    description="Select the units this occupant rents in {property.name}."
                                />
                                {units.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No units yet. Add a unit to this property before assigning
                                        occupants.
                                    </p>
                                ) : (
                                    <div className="grid gap-3">
                                        {units.map((unit) => (
                                            <label
                                                key={unit.id}
                                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={unitIds.includes(unit.id)}
                                                    onCheckedChange={() => toggleUnit(unit.id)}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="unit_ids[]"
                                                    value={String(unit.id)}
                                                    disabled={!unitIds.includes(unit.id)}
                                                />
                                                <DoorOpen className="size-4 text-muted-foreground" />
                                                <span className="font-medium">{unit.name}</span>
                                                {unit.type && (
                                                    <span className="text-muted-foreground">
                                                        {unit.type}
                                                    </span>
                                                )}
                                                <span className="ml-auto">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs ${
                                                            unit.status === 'vacant'
                                                                ? 'bg-muted text-muted-foreground'
                                                                : 'bg-primary/10 text-primary'
                                                        }`}
                                                    >
                                                        {unit.status}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                        <InputError message={errors.unit_ids} />
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add occupant'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={occupantsIndex(property.slug)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OccupantCreate.layout = {
    breadcrumbs: [
        {
            title: 'Occupants',
            href: '',
        },
        {
            title: 'Add occupant',
        },
    ],
};
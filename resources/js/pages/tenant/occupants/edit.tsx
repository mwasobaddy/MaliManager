import { Form, Head, Link, usePage } from '@inertiajs/react';
import { DoorOpen, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as destroyOccupant,
    index as occupantsIndex,
    update,
} from '@/routes/tenant/occupants';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

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

type Occupant = {
    id: number;
    first_name: string;
    last_name: string | null;
    email: string;
    phone: string | null;
    national_id: string | null;
    status: string;
    unit_ids: number[];
};

type Props = {
    organization: Organization;
    property: Property;
    occupant: Occupant;
    units: Unit[];
};

export default function OccupantEdit({ organization, property, occupant, units }: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canDelete = permissions.includes('occupant.delete');
    const passwordInput = useRef<HTMLInputElement>(null);
    const [status, setStatus] = useState(occupant.status);
    const [unitIds, setUnitIds] = useState<number[]>(occupant.unit_ids);

    const toggleUnit = (id: number) => {
        setUnitIds((prev) =>
            prev.includes(id) ? prev.filter((uid) => uid !== id) : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Edit occupant" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`Edit ${occupant.first_name} ${occupant.last_name ?? ''}`}
                        description={`Update ${property.name} occupant details and unit assignments.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={occupantsIndex(property.slug)}>Back to occupants</Link>
                    </Button>
                </div>

                <Form {...update.form({ property: property.slug, occupant: occupant.id })} className="space-y-6">
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
                                        defaultValue={occupant.first_name}
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
                                        defaultValue={occupant.last_name ?? ''}
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
                                        defaultValue={occupant.email}
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
                                        defaultValue={occupant.phone ?? ''}
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
                                        defaultValue={occupant.national_id ?? ''}
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
                                    description={`Select the units this occupant rents in ${property.name}.`}
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

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save changes'}
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={occupantsIndex(property.slug)}>Cancel</Link>
                                    </Button>
                                </div>

                                {canDelete && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button variant="destructive" type="button">
                                                <Trash2 className="size-4" />
                                                Remove occupant
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Remove {occupant.first_name}{' '}
                                                {occupant.last_name ?? ''} from {organization.name}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This will detach them from all assigned units. Please
                                                enter your password to confirm.
                                            </DialogDescription>

                                            <Form
                                                {...destroyOccupant.form({
                                                    property: property.slug,
                                                    occupant: occupant.id,
                                                })}
                                                options={{ preserveScroll: true }}
                                                onError={() => passwordInput.current?.focus()}
                                                resetOnSuccess
                                                className="space-y-6"
                                            >
                                                {({ resetAndClearErrors, processing, errors }) => (
                                                    <>
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor="password"
                                                                className="sr-only"
                                                            >
                                                                Password
                                                            </Label>

                                                            <PasswordInput
                                                                id="password"
                                                                name="password"
                                                                ref={passwordInput}
                                                                placeholder="Password"
                                                                autoComplete="current-password"
                                                            />

                                                            <InputError message={errors.password} />
                                                        </div>

                                                        <DialogFooter className="gap-2">
                                                            <DialogClose asChild>
                                                                <Button
                                                                    variant="secondary"
                                                                    onClick={() =>
                                                                        resetAndClearErrors()
                                                                    }
                                                                >
                                                                    Cancel
                                                                </Button>
                                                            </DialogClose>

                                                            <Button
                                                                variant="destructive"
                                                                disabled={processing}
                                                                asChild
                                                            >
                                                                <button
                                                                    type="submit"
                                                                    data-test="confirm-remove-occupant-button"
                                                                >
                                                                    Remove occupant
                                                                </button>
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OccupantEdit.layout = {
    breadcrumbs: [
        {
            title: 'Occupants',
            href: '',
        },
        {
            title: 'Edit occupant',
        },
    ],
};
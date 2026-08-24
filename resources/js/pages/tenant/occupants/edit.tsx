import { Form, Head, Link, usePage } from '@inertiajs/react';
import { DoorOpen, LogOut, Trash2 } from 'lucide-react';
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
    moveOut,
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

type Lease = {
    starts_at: string | null;
    rent_amount: number | null;
    rent_frequency: string | null;
    deposit: number | null;
    currency: string | null;
    agreement_text: string | null;
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
    lease: Lease | null;
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
    const canMoveOut = permissions.includes('occupant.edit');
    const passwordInput = useRef<HTMLInputElement>(null);
    const [status, setStatus] = useState(occupant.status);
    const [unitIds, setUnitIds] = useState<number[]>(occupant.unit_ids);
    const [rentFrequency, setRentFrequency] = useState(occupant.lease?.rent_frequency ?? 'monthly');
    const [currency, setCurrency] = useState(occupant.lease?.currency ?? 'KES');

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
                                    title="Lease details"
                                    description="Capture the rental terms. These power the renter's rental history."
                                />
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.starts_at">Lease start date</Label>
                                        <Input
                                            id="lease.starts_at"
                                            name="lease[starts_at]"
                                            type="date"
                                            defaultValue={occupant.lease?.starts_at ?? ''}
                                        />
                                        <InputError message={errors['lease.starts_at']} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.rent_frequency">Rent frequency</Label>
                                        <input type="hidden" name="lease[rent_frequency]" value={rentFrequency} />
                                        <Select value={rentFrequency} onValueChange={setRentFrequency}>
                                            <SelectTrigger id="lease.rent_frequency" className="w-full">
                                                <SelectValue placeholder="Select frequency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="daily">Daily</SelectItem>
                                                <SelectItem value="weekly">Weekly</SelectItem>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors['lease.rent_frequency']} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.rent_amount">Rent amount</Label>
                                        <Input
                                            id="lease.rent_amount"
                                            name="lease[rent_amount]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={occupant.lease?.rent_amount ?? ''}
                                        />
                                        <InputError message={errors['lease.rent_amount']} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.deposit">Deposit</Label>
                                        <Input
                                            id="lease.deposit"
                                            name="lease[deposit]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={occupant.lease?.deposit ?? ''}
                                        />
                                        <InputError message={errors['lease.deposit']} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.currency">Currency</Label>
                                        <input type="hidden" name="lease[currency]" value={currency} />
                                        <Select value={currency} onValueChange={setCurrency}>
                                            <SelectTrigger id="lease.currency" className="w-full">
                                                <SelectValue placeholder="Select currency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="KES">KES</SelectItem>
                                                <SelectItem value="USD">USD</SelectItem>
                                                <SelectItem value="EUR">EUR</SelectItem>
                                                <SelectItem value="GBP">GBP</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors['lease.currency']} />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="lease.agreement_text">
                                            Agreement notes (optional)
                                        </Label>
                                        <textarea
                                            id="lease.agreement_text"
                                            name="lease[agreement_text]"
                                            rows={3}
                                            defaultValue={occupant.lease?.agreement_text ?? ''}
                                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                            placeholder="Any standing agreement details for this rental."
                                        />
                                        <InputError message={errors['lease.agreement_text']} />
                                    </div>
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

                                {canMoveOut && occupant.status !== 'moved_out' && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button variant="outline" type="button">
                                                <LogOut className="size-4" />
                                                Move out
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Move {occupant.first_name}{' '}
                                                {occupant.last_name ?? ''} out?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This ends their active lease(s) — kept for their
                                                rental history — and frees their units. The renter
                                                reverts to a searcher.
                                            </DialogDescription>

                                            <Form
                                                {...moveOut.form({
                                                    property: property.slug,
                                                    occupant: occupant.id,
                                                })}
                                                options={{ preserveScroll: true }}
                                                className="space-y-6"
                                            >
                                                {({ processing }) => (
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
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
                                                                data-test="confirm-move-out-button"
                                                            >
                                                                Move out
                                                            </button>
                                                        </Button>
                                                    </DialogFooter>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                )}

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
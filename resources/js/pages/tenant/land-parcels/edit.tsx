import { Form, Head, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
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
import { destroy, edit, index } from '@/routes/tenant/land-parcels';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Manager = {
    id: number;
    name: string;
    sub_role: string | null;
};

type ParcelImage = {
    id: number;
    url: string;
    name: string;
};

type LandParcel = {
    id: number;
    name: string;
    slug: string;
    title_deed_number: string | null;
    acreage: string | null;
    zoning: string;
    address: string | null;
    city: string | null;
    status: string;
    latitude: string | null;
    longitude: string | null;
    available_for_lease: boolean;
    notes: string | null;
    manager_ids: number[];
    managers: Manager[];
    images: ParcelImage[];
};

type Props = {
    organization: Organization;
    parcel: LandParcel;
    managers: Manager[];
    canDeleteLandParcel: boolean;
};

const ZONING = [
    'residential',
    'commercial',
    'agricultural',
    'mixed',
    'industrial',
];

export default function LandParcelEdit({
    organization,
    parcel,
    managers,
    canDeleteLandParcel,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const [zoning, setZoning] = useState(parcel.zoning);
    const [status, setStatus] = useState(parcel.status);
    const [available, setAvailable] = useState(parcel.available_for_lease);
    const [managerIds, setManagerIds] = useState<number[]>(parcel.manager_ids);

    const toggleManager = (id: number) => {
        setManagerIds((prev) =>
            prev.includes(id)
                ? prev.filter((mid) => mid !== id)
                : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Edit land parcel" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`Edit ${parcel.name}`}
                        description={`Update ${organization.name} parcel details.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={index()}>Back to land parcels</Link>
                    </Button>
                </div>

                <Form {...edit.form(parcel.slug)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 rounded-xl border border-input p-6 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        autoComplete="off"
                                        defaultValue={parcel.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="title_deed_number">
                                        Title deed no. (optional)
                                    </Label>
                                    <Input
                                        id="title_deed_number"
                                        name="title_deed_number"
                                        type="text"
                                        autoComplete="off"
                                        defaultValue={
                                            parcel.title_deed_number ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.title_deed_number}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="acreage">
                                        Acreage (optional)
                                    </Label>
                                    <Input
                                        id="acreage"
                                        name="acreage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        autoComplete="off"
                                        defaultValue={parcel.acreage ?? ''}
                                    />
                                    <InputError message={errors.acreage} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="zoning">Zoning</Label>
                                    <input
                                        type="hidden"
                                        name="zoning"
                                        value={zoning}
                                    />
                                    <Select
                                        value={zoning}
                                        onValueChange={setZoning}
                                    >
                                        <SelectTrigger
                                            id="zoning"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select zoning" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ZONING.map((z) => (
                                                <SelectItem key={z} value={z}>
                                                    {z}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.zoning} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">
                                        Address (optional)
                                    </Label>
                                    <Input
                                        id="address"
                                        name="address"
                                        type="text"
                                        autoComplete="off"
                                        defaultValue={parcel.address ?? ''}
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="city">
                                        City (optional)
                                    </Label>
                                    <Input
                                        id="city"
                                        name="city"
                                        type="text"
                                        autoComplete="off"
                                        defaultValue={parcel.city ?? ''}
                                    />
                                    <InputError message={errors.city} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="latitude">
                                        Latitude (optional)
                                    </Label>
                                    <Input
                                        id="latitude"
                                        name="latitude"
                                        type="number"
                                        step="any"
                                        autoComplete="off"
                                        defaultValue={parcel.latitude ?? ''}
                                    />
                                    <InputError message={errors.latitude} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="longitude">
                                        Longitude (optional)
                                    </Label>
                                    <Input
                                        id="longitude"
                                        name="longitude"
                                        type="number"
                                        step="any"
                                        autoComplete="off"
                                        defaultValue={parcel.longitude ?? ''}
                                    />
                                    <InputError message={errors.longitude} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={status}
                                    />
                                    <Select
                                        value={status}
                                        onValueChange={setStatus}
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                Active
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Inactive
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="available_for_lease">
                                        Available for lease
                                    </Label>
                                    <div className="flex items-center gap-2 pt-2">
                                        <Checkbox
                                            id="available_for_lease"
                                            checked={available}
                                            onCheckedChange={(checked) =>
                                                setAvailable(checked === true)
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="available_for_lease"
                                            value={available ? '1' : '0'}
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            List this parcel as available for
                                            lease
                                        </span>
                                    </div>
                                    <InputError
                                        message={errors.available_for_lease}
                                    />
                                </div>

                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="notes">
                                        Notes (optional)
                                    </Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={3}
                                        defaultValue={parcel.notes ?? ''}
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                {parcel.images.length > 0 && (
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label>Current photos</Label>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            {parcel.images.map((image) => (
                                                <img
                                                    key={image.id}
                                                    src={image.url}
                                                    alt={image.name}
                                                    className="h-32 w-full rounded-xl border border-input object-cover"
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="images">
                                        Replace / add photos (optional)
                                    </Label>
                                    <input
                                        id="images"
                                        name="images[]"
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        className="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border file:border-input file:bg-muted file:px-3 file:py-1 file:text-foreground"
                                    />
                                    <InputError message={errors.images} />
                                </div>
                            </div>

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Managers"
                                    description="Select the staff members who can manage this parcel."
                                />
                                {managers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No staff members yet. Add staff before
                                        delegating management.
                                    </p>
                                ) : (
                                    <div className="grid gap-3">
                                        {managers.map((manager) => (
                                            <label
                                                key={manager.id}
                                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={managerIds.includes(
                                                        manager.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleManager(
                                                            manager.id,
                                                        )
                                                    }
                                                />
                                                <input
                                                    type="hidden"
                                                    name="manager_ids[]"
                                                    value={String(manager.id)}
                                                    disabled={
                                                        !managerIds.includes(
                                                            manager.id,
                                                        )
                                                    }
                                                />
                                                <span className="font-medium">
                                                    {manager.name}
                                                </span>
                                                {manager.sub_role && (
                                                    <span className="text-muted-foreground">
                                                        {manager.sub_role}
                                                    </span>
                                                )}
                                            </label>
                                        ))}
                                        <InputError
                                            message={errors.manager_ids}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save changes'}
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={index()}>Cancel</Link>
                                    </Button>
                                </div>

                                {canDeleteLandParcel && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="destructive"
                                                type="button"
                                            >
                                                <Trash2 className="size-4" />
                                                Remove parcel
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Remove {parcel.name} from{' '}
                                                {organization.name}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This permanently deletes the
                                                parcel and its photos. Please
                                                enter your password to confirm.
                                            </DialogDescription>

                                            <Form
                                                {...destroy.form(parcel.slug)}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                onError={() =>
                                                    passwordInput.current?.focus()
                                                }
                                                resetOnSuccess
                                                className="space-y-6"
                                            >
                                                {({
                                                    resetAndClearErrors,
                                                    processing,
                                                    errors,
                                                }) => (
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
                                                                ref={
                                                                    passwordInput
                                                                }
                                                                placeholder="Password"
                                                                autoComplete="current-password"
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors.password
                                                                }
                                                            />
                                                        </div>

                                                        <DialogFooter className="gap-2">
                                                            <DialogClose
                                                                asChild
                                                            >
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
                                                                disabled={
                                                                    processing
                                                                }
                                                                asChild
                                                            >
                                                                <button
                                                                    type="submit"
                                                                    data-test="confirm-remove-land-parcel-button"
                                                                >
                                                                    Remove
                                                                    parcel
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

LandParcelEdit.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
        {
            title: 'Edit land parcel',
        },
    ],
};

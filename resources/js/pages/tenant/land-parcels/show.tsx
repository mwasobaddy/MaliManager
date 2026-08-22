import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { edit, destroy, index } from '@/routes/tenant/land-parcels';

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
    managers: Manager[];
    images: ParcelImage[];
};

type Props = {
    organization: Organization;
    parcel: LandParcel;
    managers: Manager[];
    canEditLandParcel: boolean;
    canDeleteLandParcel: boolean;
};

const Detail = ({
    label,
    value,
    full = false,
}: {
    label: string;
    value: React.ReactNode;
    full?: boolean;
}) => (
    <div className={`grid gap-1${full ? ' sm:col-span-2' : ''}`}>
        <dt className="text-sm text-muted-foreground">{label}</dt>
        <dd className="text-sm font-medium">
            {value ?? <span className="text-muted-foreground">—</span>}
        </dd>
    </div>
);

export default function LandParcelShow({ organization, parcel, managers, canEditLandParcel, canDeleteLandParcel }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title={parcel.name} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={parcel.name}
                        description={`${organization.name} land parcel`}
                    />
                    <div className="flex gap-3">
                        <Button asChild variant="outline">
                            <Link href={index()}>Back to land parcels</Link>
                        </Button>
                        {canEditLandParcel && (
                            <Button asChild>
                                <Link href={edit(parcel.slug)}>
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {parcel.images.length > 0 && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {parcel.images.map((image) => (
                                    <img
                                        key={image.id}
                                        src={image.url}
                                        alt={image.name}
                                        className="h-48 w-full rounded-xl border border-input object-cover"
                                    />
                                ))}
                            </div>
                        )}

                        <div className="rounded-xl border border-input p-6">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail label="Title deed no." value={parcel.title_deed_number} />
                                <Detail label="Acreage" value={parcel.acreage ? `${parcel.acreage} acres` : null} />
                                <Detail label="Zoning" value={parcel.zoning} />
                                <Detail label="Address" value={parcel.address} />
                                <Detail label="City" value={parcel.city} />
                                <Detail
                                    label="Status"
                                    value={
                                        <Badge variant={parcel.status === 'active' ? 'default' : 'secondary'}>
                                            {parcel.status}
                                        </Badge>
                                    }
                                />
                                <Detail label="Latitude" value={parcel.latitude} />
                                <Detail label="Longitude" value={parcel.longitude} />
                                <Detail
                                    label="Available for lease"
                                    value={parcel.available_for_lease ? 'Yes' : 'No'}
                                />
                                <Detail label="Notes" value={parcel.notes} full />
                            </dl>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-input p-6">
                            <h3 className="text-sm font-semibold">Managers</h3>
                            {managers.length === 0 ? (
                                <p className="mt-2 text-sm text-muted-foreground">No managers assigned.</p>
                            ) : (
                                <ul className="mt-3 space-y-2 text-sm">
                                    {managers.map((manager) => (
                                        <li key={manager.id} className="flex items-center gap-2">
                                            <span className="font-medium">{manager.name}</span>
                                            {manager.sub_role && (
                                                <span className="text-muted-foreground">{manager.sub_role}</span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {canDeleteLandParcel && (
                            <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-6">
                                <h3 className="text-sm font-semibold text-destructive">Danger zone</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Removing this parcel is permanent and cannot be undone.
                                </p>
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button variant="destructive" className="mt-4" type="button">
                                            <Trash2 className="size-4" />
                                            Remove parcel
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>Remove {parcel.name} from {organization.name}?</DialogTitle>
                                        <DialogDescription>
                                            This permanently deletes the parcel and its photos. Please enter
                                            your password to confirm.
                                        </DialogDescription>

                                        <Form
                                            {...destroy.form(parcel.slug)}
                                            options={{ preserveScroll: true }}
                                            onError={() => passwordInput.current?.focus()}
                                            resetOnSuccess
                                            className="space-y-6"
                                        >
                                            {({ resetAndClearErrors, processing, errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="password" className="sr-only">
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
                                                                onClick={() => resetAndClearErrors()}
                                                            >
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button variant="destructive" disabled={processing} asChild>
                                                            <button
                                                                type="submit"
                                                                data-test="confirm-remove-land-parcel-button"
                                                            >
                                                                Remove parcel
                                                            </button>
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

LandParcelShow.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
        {
            title: 'Parcel',
        },
    ],
};

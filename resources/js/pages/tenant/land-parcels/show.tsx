import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, edit, index } from '@/routes/tenant/land-parcels';
import { destroy as destroySection, lease, store } from '@/routes/tenant/land-parcel-sections';

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

type Section = {
    id: number;
    name: string;
    area: string | null;
    status: string;
    notes: string | null;
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
    sections: Section[];
    canEditLandParcel: boolean;
    canDeleteLandParcel: boolean;
    canManageSections: boolean;
    canLease: boolean;
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

export default function LandParcelShow({
    organization,
    parcel,
    managers,
    sections,
    canEditLandParcel,
    canDeleteLandParcel,
    canManageSections,
    canLease,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const [addSection, setAddSection] = useState(false);
    const [leaseSectionId, setLeaseSectionId] = useState<number | null>(null);
    const [agreementHtml, setAgreementHtml] = useState<string | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const html = flash?.agreement_html as string | undefined;

            if (html) {
                setAgreementHtml(html);
            }
        });
    }, []);

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

                        {/* Sections: sub-plots that can be leased independently. */}
                        <div className="rounded-xl border border-input p-6">
                            <div className="flex items-center justify-between gap-4">
                                <h3 className="text-sm font-semibold">Sections</h3>
                                {canManageSections && !addSection && (
                                    <Button size="sm" onClick={() => setAddSection(true)}>
                                        <Plus className="size-4" />
                                        Add section
                                    </Button>
                                )}
                            </div>

                            {addSection && (
                                <Form
                                    {...store.form({ land_parcel: parcel.slug })}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setAddSection(false)}
                                    className="my-4 grid gap-4 rounded-lg border border-input p-4 md:grid-cols-[1fr_1fr_1fr_auto]"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="section-name">Name</Label>
                                                <Input id="section-name" name="name" required placeholder="Block A" />
                                                <InputError message={errors.name} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="section-area">Area (acres)</Label>
                                                <Input id="section-area" name="area" type="number" step="0.01" min="0" placeholder="2.5" />
                                                <InputError message={errors.area} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="section-status">Status</Label>
                                                <select
                                                    id="section-status"
                                                    name="status"
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                                    defaultValue="vacant"
                                                >
                                                    <option value="vacant">Vacant</option>
                                                    <option value="leased">Leased</option>
                                                    <option value="maintenance">Maintenance</option>
                                                </select>
                                                <InputError message={errors.status} />
                                            </div>
                                            <div className="flex items-end gap-2">
                                                <Button type="submit" disabled={processing}>
                                                    {processing ? 'Saving…' : 'Add'}
                                                </Button>
                                                <Button type="button" variant="ghost" onClick={() => setAddSection(false)}>
                                                    Cancel
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            )}

                            {sections.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    No sections yet. Add a section to lease part of this parcel.
                                </p>
                            ) : (
                                <ul className="mt-4 divide-y divide-border rounded-lg border">
                                    {sections.map((section) => (
                                        <li key={section.id} className="p-4">
                                            <div className="flex items-center justify-between gap-4">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{section.name}</p>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {section.area ? `${section.area} acres` : 'No area set'}
                                                        {section.notes ? ` · ${section.notes}` : ''}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Badge
                                                        variant={section.status === 'leased' ? 'default' : 'secondary'}
                                                    >
                                                        {section.status}
                                                    </Badge>
                                                    {canLease && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setLeaseSectionId(
                                                                    leaseSectionId === section.id ? null : section.id,
                                                                )
                                                            }
                                                        >
                                                            <FileText className="size-4" />
                                                            {leaseSectionId === section.id ? 'Close' : 'Lease'}
                                                        </Button>
                                                    )}
                                                    {canManageSections && (
                                                        <Form
                                                            {...destroySection.form({
                                land_parcel: parcel.slug,
                                land_parcel_section: section.id,
                            })}
                                                            options={{ preserveScroll: true }}
                                                        >
                                                            <Button size="sm" variant="ghost" type="submit">
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </Form>
                                                    )}
                                                </div>
                                            </div>

                                            {leaseSectionId === section.id && (
                                                <Form
                                                    {...lease.form({
                                land_parcel: parcel.slug,
                                land_parcel_section: section.id,
                            })}
                                                    options={{ preserveScroll: true }}
                                                    onSuccess={() => setLeaseSectionId(null)}
                                                    className="mt-4 grid gap-4 rounded-lg border border-input p-4 md:grid-cols-2"
                                                >
                                                    {({ processing, errors }) => (
                                                        <>
                                                            <div className="grid gap-2 md:col-span-2">
                                <p className="text-sm font-medium">
                                    Lease <span className="text-foreground">{section.name}</span> to a tenant
                                </p>
                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`tenant-name-${section.id}`}>Tenant name</Label>
                                                                <Input
                                                                    id={`tenant-name-${section.id}`}
                                                                    name="tenant_name"
                                                                    placeholder="Jane Doe"
                                                                />
                                                                <InputError message={errors.tenant_name} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`tenant-email-${section.id}`}>Tenant email</Label>
                                                                <Input
                                                                    id={`tenant-email-${section.id}`}
                                                                    name="tenant_email"
                                                                    type="email"
                                                                    placeholder="jane@example.com"
                                                                />
                                                                <InputError message={errors.tenant_email} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`rent-${section.id}`}>Rent amount</Label>
                                                                <Input
                                                                    id={`rent-${section.id}`}
                                                                    name="rent_amount"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    required
                                                                    placeholder="45000"
                                                                />
                                                                <InputError message={errors.rent_amount} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`freq-${section.id}`}>Frequency</Label>
                                                                <Input
                                                                    id={`freq-${section.id}`}
                                                                    name="rent_frequency"
                                                                    placeholder="monthly"
                                                                />
                                                                <InputError message={errors.rent_frequency} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`currency-${section.id}`}>Currency</Label>
                                                                <Input
                                                                    id={`currency-${section.id}`}
                                                                    name="currency"
                                                                    placeholder="KES"
                                                                />
                                                                <InputError message={errors.currency} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`deposit-${section.id}`}>Deposit</Label>
                                                                <Input
                                                                    id={`deposit-${section.id}`}
                                                                    name="deposit"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    placeholder="90000"
                                                                />
                                                                <InputError message={errors.deposit} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`starts-${section.id}`}>Start date</Label>
                                                                <Input
                                                                    id={`starts-${section.id}`}
                                                                    name="starts_at"
                                                                    type="date"
                                                                    required
                                                                />
                                                                <InputError message={errors.starts_at} />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor={`ends-${section.id}`}>End date</Label>
                                                                <Input
                                                                    id={`ends-${section.id}`}
                                                                    name="ends_at"
                                                                    type="date"
                                                                />
                                                                <InputError message={errors.ends_at} />
                                                            </div>
                                                            <div className="flex items-end gap-2 md:col-span-2">
                                                                <Button type="submit" disabled={processing}>
                                                                    {processing ? 'Creating…' : 'Create lease'}
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    onClick={() => setLeaseSectionId(null)}
                                                                >
                                                                    Cancel
                                                                </Button>
                                                            </div>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
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

            <Dialog open={agreementHtml !== null} onOpenChange={(open) => !open && setAgreementHtml(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogTitle>Lease agreement</DialogTitle>
                    <DialogDescription>
                        Generated from your organization's lease template and the lease details.
                    </DialogDescription>
                    {agreementHtml && (
                        <div
                            className="prose prose-sm max-w-none overflow-y-auto text-sm"
                            dangerouslySetInnerHTML={{ __html: agreementHtml }}
                        />
                    )}
                    <DialogFooter>
                        <Button onClick={() => setAgreementHtml(null)}>Close</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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

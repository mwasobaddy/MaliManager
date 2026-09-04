import { Form, Head, Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
import { store } from '@/routes/tenant/land-parcels';

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

type Props = {
    organization: Organization;
    managers: Manager[];
    parcel: null;
    selected_manager_ids: number[];
};

const ZONING = [
    'residential',
    'commercial',
    'agricultural',
    'mixed',
    'industrial',
];

export default function LandParcelCreate({
    organization,
    managers,
    selected_manager_ids,
}: Props) {
    const [zoning, setZoning] = useState('residential');
    const [status, setStatus] = useState('active');
    const [available, setAvailable] = useState(false);
    const [managerIds, setManagerIds] =
        useState<number[]>(selected_manager_ids);
    const imagesRef = useRef<HTMLInputElement>(null);

    const toggleManager = (id: number) => {
        setManagerIds((prev) =>
            prev.includes(id)
                ? prev.filter((mid) => mid !== id)
                : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Add land parcel" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Add a land parcel"
                        description={`Record a plot of land for ${organization.name}.`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/land-parcels">Back to land parcels</Link>
                    </Button>
                </div>

                <Form {...store.form()} className="space-y-6">
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
                                        placeholder="Ruiru Plot A"
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
                                        placeholder="LR/12345"
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
                                        placeholder="2.5"
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
                                        placeholder="Off Thika Road"
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
                                        placeholder="Ruiru"
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
                                        placeholder="-1.123456"
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
                                        placeholder="36.123456"
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
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        placeholder="Anything worth noting about this parcel."
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="images">
                                        Photos (optional)
                                    </Label>
                                    <input
                                        id="images"
                                        ref={imagesRef}
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

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add land parcel'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href="/land-parcels">Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LandParcelCreate.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
        {
            title: 'Add land parcel',
        },
    ],
};

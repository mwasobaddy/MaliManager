import { Form, Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FrequencySelect } from '@/components/frequency-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectLabel,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { firstAsset } from '@/routes/onboarding';
import { show as billing } from '@/routes/tenant/billing';
import { index } from '@/routes/tenant/properties';
import { store } from '@/routes/tenant/properties';

export type AssetPropertyType = {
    id: number;
    name: string;
    category: 'residential' | 'commercial';
};

export type AssetPlan = {
    properties_limit: number | null;
    units_limit: number | null;
};

type UnitDraft = {
    name: string;
    type: string;
    rent_frequency: string;
    rent_amount: string;
    /**
     * File inputs cannot be controlled, so photo counts live here and the
     * inputs are rendered with a `key` that changes when the count does —
     * that is what actually resets them. Storing `FileList` in state would
     * also work but would force a re-render on every picked file.
     */
    photoCount: number;
};

const emptyUnit = (): UnitDraft => ({
    name: '',
    type: '',
    rent_frequency: 'monthly',
    rent_amount: '',
    photoCount: 0,
});

/** Mirrors `Unit::MAX_PHOTOS` on the server. */
const MAX_UNIT_PHOTOS = 12;

/**
 * The property create form shared by the app shell
 * (`organisation/properties/create`) and the setup funnel
 * (`setup/property`). The fields and the store endpoint are identical —
 * only the surrounding layout differs, so the layout lives in the pages
 * and the form lives here. `setup` keeps the onboarding funnel moving to
 * the team step instead of landing on the dashboard.
 */
export type AssetBranch = {
    id: number;
    name: string;
};

export default function PropertyAssetForm({
    plan,
    propertyTypes,
    branches,
    defaultBranchId,
    canCreateProperty,
    setup,
}: {
    plan: AssetPlan;
    propertyTypes: AssetPropertyType[];
    branches: AssetBranch[];
    defaultBranchId?: number | null;
    canCreateProperty: boolean;
    setup: boolean;
}) {
    const [units, setUnits] = useState<UnitDraft[]>([]);
    const [pasteOpen, setPasteOpen] = useState(false);
    const [pasteText, setPasteText] = useState('');
    const [pasteNote, setPasteNote] = useState<string | null>(null);

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

    /**
     * One unit name per line. Blanks and names already on the form are
     * skipped, and the batch stops at the effective limit (plan cap or
     * the server's per-request cap) so the submit never fails after
     * all that typing.
     */
    function addPastedUnits() {
        const limit = Math.min(plan.units_limit ?? 100, 100);
        const seen = new Set(
            units.map((unit) => unit.name.trim().toLowerCase()),
        );
        const fresh: string[] = [];
        let skipped = 0;

        for (const line of pasteText.split('\n')) {
            const name = line.trim();

            if (name === '' || seen.has(name.toLowerCase())) {
                skipped += 1;
                continue;
            }

            seen.add(name.toLowerCase());
            fresh.push(name);
        }

        const room = Math.max(0, limit - units.length);
        const taken = fresh.slice(0, room);

        setUnits((prev) => [
            ...prev,
            ...taken.map((name) => ({ ...emptyUnit(), name })),
        ]);
        setPasteText('');
        setPasteOpen(false);

        const parts = [
            `Added ${taken.length} ${taken.length === 1 ? 'unit' : 'units'}.`,
        ];

        if (skipped > 0) {
            parts.push(
                `${skipped} blank or duplicate ${skipped === 1 ? 'line' : 'lines'} skipped.`,
            );
        }

        if (fresh.length > taken.length) {
            parts.push(`Limit is ${limit} units — the rest were left out.`);
        }

        setPasteNote(parts.join(' '));
    }

    if (!canCreateProperty) {
        return (
            <Card>
                <CardContent className="space-y-3 pt-6">
                    <p className="text-sm text-destructive">
                        Your current plan does not allow adding more properties.
                    </p>
                    <Button asChild variant="outline" size="sm">
                        <Link href={billing.url()}>Upgrade in Billing</Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    /*
     * encType is multipart because the unit rows post file
     * inputs. Without it the browser sends a urlencoded body
     * that silently drops every file — the property would be
     * created and the photos quietly discarded, with no error
     * anywhere to explain why.
     */
    return (
        <Form
            {...store.form(setup ? { query: { setup: 1 } } : undefined)}
            encType="multipart/form-data"
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="setup"
                        value={setup ? '1' : ''}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Property details
                            </CardTitle>
                            <CardDescription>
                                What the building is called and where it is.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Property name</Label>
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

                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="location-url">
                                    Location link
                                </Label>
                                <Input
                                    id="location-url"
                                    name="location_url"
                                    type="url"
                                    inputMode="url"
                                    placeholder="https://maps.app.goo.gl/..."
                                />
                                <p className="text-xs text-muted-foreground">
                                    A Google Maps, Plus Code or OpenStreetMap
                                    link. Kept separate from the address above,
                                    so the address stays prose and this stays
                                    something a button can open.
                                </p>
                                <InputError message={errors.location_url} />
                            </div>

                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="property-type">
                                    Property type
                                </Label>
                                <Select name="property_type_id">
                                    <SelectTrigger id="property-type">
                                        <SelectValue placeholder="Choose a type (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            Not set
                                        </SelectItem>
                                        {['residential', 'commercial'].map(
                                            (category) => {
                                                const group =
                                                    propertyTypes.filter(
                                                        (type) =>
                                                            type.category ===
                                                            category,
                                                    );

                                                if (group.length === 0) {
                                                    return null;
                                                }

                                                return (
                                                    <SelectGroup key={category}>
                                                        <SelectLabel>
                                                            {category ===
                                                            'residential'
                                                                ? 'Residential'
                                                                : 'Commercial'}
                                                        </SelectLabel>
                                                        {group.map((type) => (
                                                            <SelectItem
                                                                key={type.id}
                                                                value={String(
                                                                    type.id,
                                                                )}
                                                            >
                                                                {type.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                );
                                            },
                                        )}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Manage these on the property types page.
                                </p>
                                <InputError message={errors.property_type_id} />
                            </div>

                            {branches.length > 1 ? (
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="branch">Branch</Label>
                                    <Select
                                        name="branch_id"
                                        defaultValue={
                                            defaultBranchId
                                                ? String(defaultBranchId)
                                                : undefined
                                        }
                                    >
                                        <SelectTrigger id="branch">
                                            <SelectValue placeholder="Choose a branch" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {branches.map((branch) => (
                                                <SelectItem
                                                    key={branch.id}
                                                    value={String(branch.id)}
                                                >
                                                    {branch.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.branch_id} />
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle className="text-base font-medium">
                                        Units
                                    </CardTitle>
                                    <CardDescription>
                                        {`Optional for now. ${plan.units_limit ? `Your plan allows up to ${plan.units_limit} units per property.` : 'No unit limit on your plan.'}`}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setPasteOpen((open) => !open)
                                        }
                                    >
                                        Paste a list
                                    </Button>
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
                            </div>
                        </CardHeader>
                        <CardContent>
                            {pasteOpen ? (
                                <div className="mb-4 grid gap-2">
                                    <Label htmlFor="units-paste">
                                        One unit name per line
                                    </Label>
                                    <Textarea
                                        id="units-paste"
                                        rows={4}
                                        placeholder={'A1\nA2\nA3'}
                                        value={pasteText}
                                        onChange={(e) =>
                                            setPasteText(e.target.value)
                                        }
                                    />
                                    <div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={pasteText.trim() === ''}
                                            onClick={addPastedUnits}
                                        >
                                            Add units
                                        </Button>
                                    </div>
                                </div>
                            ) : null}
                            {pasteNote ? (
                                <p
                                    className="mb-4 text-sm text-muted-foreground"
                                    role="status"
                                >
                                    {pasteNote}
                                </p>
                            ) : null}
                            {units.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    You can add units now or later from the
                                    property page.
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
                                                            e.target.value,
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
                                                            e.target.value,
                                                        )
                                                    }
                                                    name={`units[${i}][type]`}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`units-${i}-frequency`}
                                                >
                                                    Billing frequency
                                                </Label>
                                                <FrequencySelect
                                                    id={`units-${i}-frequency`}
                                                    name={`units[${i}][rent_frequency]`}
                                                    defaultValue={
                                                        unit.rent_frequency ||
                                                        'monthly'
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.rent_frequency`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`units-${i}-rent`}
                                                >
                                                    Rent amount
                                                </Label>
                                                <Input
                                                    id={`units-${i}-rent`}
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="25000"
                                                    value={unit.rent_amount}
                                                    onChange={(e) =>
                                                        updateUnit(
                                                            i,
                                                            'rent_amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                    name={`units[${i}][rent_amount]`}
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.rent_amount`
                                                        ]
                                                    }
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
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>

                                            {/*
                                             * Photos get their own row so
                                             * the three text fields keep
                                             * their single-line height —
                                             * a file input in that grid
                                             * would stretch every column.
                                             */}
                                            <div className="grid gap-2 md:col-span-4">
                                                <Label
                                                    htmlFor={`units-${i}-photos`}
                                                >
                                                    Photos (optional, up to{' '}
                                                    {MAX_UNIT_PHOTOS})
                                                </Label>
                                                <Input
                                                    /*
                                                     * `key` changes with
                                                     * the count so React
                                                     * remounts the input and
                                                     * the browser really
                                                     * clears its FileList —
                                                     * a controlled value is
                                                     * not possible here.
                                                     */
                                                    key={`units-${i}-photos-${unit.photoCount}`}
                                                    id={`units-${i}-photos`}
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    multiple
                                                    onChange={(e) =>
                                                        setUnits((prev) =>
                                                            prev.map((u, j) =>
                                                                j === i
                                                                    ? {
                                                                          ...u,
                                                                          photoCount:
                                                                              e
                                                                                  .target
                                                                                  .files
                                                                                  ?.length ??
                                                                              0,
                                                                      }
                                                                    : u,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {unit.photoCount > 0
                                                        ? `${unit.photoCount} selected. `
                                                        : ''}
                                                    Stored under this property
                                                    and unit.
                                                </p>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.photos`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button asChild variant="shadcndefault">
                            {setup ? (
                                <Link href={firstAsset.url()}>Cancel</Link>
                            ) : (
                                <Link href={index()}>Cancel</Link>
                            )}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Create property'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

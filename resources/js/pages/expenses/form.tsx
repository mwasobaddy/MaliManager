import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    index as expensesIndexRoute,
    store as storeExpense,
    update as updateExpense,
} from '@/routes/tenant/expenses';

type Target = {
    id: number;
    name: string;
    slug: string;
    units?: { id: number; name: string }[];
};

type Targets = {
    properties: Target[];
    land_parcels: Target[];
};

type Expense = {
    id: number;
    expenseable_type: 'property' | 'land_parcel';
    expenseable_id: number;
    unit_id: number | null;
    category: string;
    amount: string | number | null;
    currency: string | null;
    spent_on: string | null;
    notes: string | null;
    receipt_name?: string | null;
    receipt_url?: string | null;
};

type Props = {
    expense: Expense | null;
    targets: Targets;
};

export default function ExpenseForm({ expense, targets }: Props) {
    const [assetType, setAssetType] = useState<'property' | 'land_parcel'>(
        (expense?.expenseable_type as 'property' | 'land_parcel') ?? 'property',
    );
    const [assetId, setAssetId] = useState<string>(
        expense ? String(expense.expenseable_id) : '',
    );
    const [unitId, setUnitId] = useState<string>(
        expense?.unit_id ? String(expense.unit_id) : '',
    );

    const form = useForm<{
        expenseable_type: 'property' | 'land_parcel';
        expenseable_id: string;
        unit_id: string;
        category: string;
        amount: string;
        currency: string;
        spent_on: string;
        notes: string;
        receipt: File | null;
        remove_receipt: boolean;
    }>({
        expenseable_type: assetType,
        expenseable_id: assetId,
        unit_id: unitId,
        category: expense?.category ?? '',
        amount: expense?.amount != null ? String(expense.amount) : '',
        currency: expense?.currency ?? 'KES',
        spent_on: expense?.spent_on ?? new Date().toISOString().slice(0, 10),
        notes: expense?.notes ?? '',
        receipt: null,
        remove_receipt: false,
    });

    const assets: Target[] =
        assetType === 'property' ? targets.properties : targets.land_parcels;

    const selectedProperty =
        assetType === 'property'
            ? targets.properties.find((p) => String(p.id) === assetId)
            : undefined;

    const setAsset = (type: 'property' | 'land_parcel', id: string) => {
        setAssetType(type);
        setAssetId(id);
        setUnitId('');
        form.setData('expenseable_type', type);
        form.setData('expenseable_id', id);
        form.setData('unit_id', '');
    };

    const submit = () => {
        if (expense) {
            form.put(updateExpense({ expense: expense.id }).url);
        } else {
            form.post(storeExpense().url);
        }
    };

    return (
        <>
            <Head title={expense ? 'Edit expense' : 'Record expense'} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title={expense ? 'Edit expense' : 'Record expense'}
                    description="Costs against a property or land parcel — repairs, renovation, cleaning and more."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Expense details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Asset type</Label>
                            <Select
                                value={assetType}
                                onValueChange={(v) =>
                                    setAsset(
                                        v as 'property' | 'land_parcel',
                                        '',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="property">
                                        Property
                                    </SelectItem>
                                    <SelectItem value="land_parcel">
                                        Land parcel
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label>
                                {assetType === 'property'
                                    ? 'Property'
                                    : 'Land parcel'}
                            </Label>
                            <Select
                                value={assetId}
                                onValueChange={(v) => {
                                    setAssetId(v);
                                    form.setData('expenseable_id', v);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pick one…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {assets.map((asset) => (
                                        <SelectItem
                                            key={asset.id}
                                            value={String(asset.id)}
                                        >
                                            {asset.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {assetType === 'property' && selectedProperty && (
                            <div className="grid gap-2">
                                <Label>Unit (optional)</Label>
                                <Select
                                    value={unitId}
                                    onValueChange={(v) => {
                                        setUnitId(v);
                                        form.setData('unit_id', v);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Whole property" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">
                                            Whole property
                                        </SelectItem>
                                        {(selectedProperty.units ?? []).map(
                                            (unit) => (
                                                <SelectItem
                                                    key={unit.id}
                                                    value={String(unit.id)}
                                                >
                                                    {unit.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label>Category</Label>
                            <Select
                                value={form.data.category}
                                onValueChange={(v) =>
                                    form.setData('category', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pick a category…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'maintenance',
                                        'renovation',
                                        'cleaning',
                                        'utilities',
                                        'security',
                                        'other',
                                    ].map((c) => (
                                        <SelectItem
                                            key={c}
                                            value={c}
                                            className="capitalize"
                                        >
                                            {c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.category} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="amount">Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                            />
                            <InputError message={form.errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="currency">Currency</Label>
                            <Input
                                id="currency"
                                maxLength={3}
                                value={form.data.currency}
                                onChange={(e) =>
                                    form.setData(
                                        'currency',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.currency} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="spent_on">Date</Label>
                            <Input
                                id="spent_on"
                                type="date"
                                value={form.data.spent_on}
                                onChange={(e) =>
                                    form.setData('spent_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.spent_on} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <textarea
                                id="notes"
                                rows={3}
                                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="receipt">
                                {expense?.receipt_url
                                    ? 'Replace receipt (PDF/Image, max 10MB)'
                                    : 'Receipt (PDF/Image, optional, max 10MB)'}
                            </Label>
                            {expense?.receipt_url && (
                                <div className="flex items-center gap-3 text-sm">
                                    <a
                                        href={expense.receipt_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary underline"
                                    >
                                        {expense.receipt_name ??
                                            'Current receipt'}
                                    </a>
                                    <label className="flex items-center gap-1 text-muted-foreground">
                                        <input
                                            type="checkbox"
                                            checked={form.data.remove_receipt}
                                            onChange={(e) =>
                                                form.setData(
                                                    'remove_receipt',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Remove
                                    </label>
                                </div>
                            )}
                            <Input
                                id="receipt"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(e) =>
                                    form.setData(
                                        'receipt',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.receipt} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={expensesIndexRoute()}>
                            Back to expenses
                        </Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        {expense ? 'Save changes' : 'Record expense'}
                    </Button>
                </div>
            </div>
        </>
    );
}

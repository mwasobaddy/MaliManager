import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
import * as expenseRoutes from '@/routes/platform/expenses';

const CATEGORIES = [
    'hosting',
    'domain',
    'tooling',
    'software',
    'marketing',
    'other',
];

type ExpenseData = {
    category: string;
    amount: string;
    currency: string;
    spent_on: string;
    vendor_name: string;
    notes: string;
};

type Expense = {
    id: number;
    category: string;
    amount: number;
    currency: string;
    spent_on: string;
    vendor_name: string | null;
    notes: string | null;
};

type Props = {
    expense?: Expense;
};

export default function PlatformExpenseForm({ expense }: Props) {
    const isEdit = !!expense;

    const form = useForm<ExpenseData>({
        category: expense?.category ?? '',
        amount: expense?.amount != null ? String(expense.amount) : '',
        currency: expense?.currency ?? 'KES',
        spent_on: expense?.spent_on ?? '',
        vendor_name: expense?.vendor_name ?? '',
        notes: expense?.notes ?? '',
    });

    form.transform((data) => ({
        ...data,
        amount: Number(data.amount),
        vendor_name: data.vendor_name === '' ? null : data.vendor_name,
        notes: data.notes === '' ? null : data.notes,
    }));

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit && expense) {
            form.patch(expenseRoutes.update(expense.id).url);
        } else {
            form.post(expenseRoutes.store().url);
        }
    }

    return (
        <>
            <Head
                title={
                    isEdit ? 'Edit platform expense' : 'New platform expense'
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={expenseRoutes.index().url}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={
                            isEdit
                                ? 'Edit platform expense'
                                : 'New platform expense'
                        }
                        description="Record a platform-level operating cost."
                    />
                </div>

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Expense</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="category">Category</Label>
                                <Select
                                    value={form.data.category}
                                    onValueChange={(v) =>
                                        form.setData('category', v)
                                    }
                                >
                                    <SelectTrigger id="category">
                                        <SelectValue placeholder="Select a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CATEGORIES.map((category) => (
                                            <SelectItem
                                                key={category}
                                                value={category}
                                            >
                                                {category}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.category} />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        value={form.data.amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input
                                        id="currency"
                                        name="currency"
                                        maxLength={3}
                                        value={form.data.currency}
                                        onChange={(e) =>
                                            form.setData(
                                                'currency',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.currency}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="spent_on">Spent on</Label>
                                <Input
                                    id="spent_on"
                                    name="spent_on"
                                    type="date"
                                    value={form.data.spent_on}
                                    onChange={(e) =>
                                        form.setData('spent_on', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.spent_on} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="vendor_name">Vendor</Label>
                                <Input
                                    id="vendor_name"
                                    name="vendor_name"
                                    value={form.data.vendor_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'vendor_name',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.vendor_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={form.errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2 lg:col-span-2">
                        <Button variant="outline" asChild>
                            <Link href={expenseRoutes.index().url}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Save changes' : 'Record expense'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

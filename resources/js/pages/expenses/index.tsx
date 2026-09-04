import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Receipt } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create,
    edit,
    index as expensesIndexRoute,
} from '@/routes/tenant/expenses';

type ExpenseRow = {
    id: number;
    asset_name: string | null;
    asset_type: string | null;
    unit_name: string | null;
    category: string;
    amount: string | null;
    currency: string | null;
    spent_on: string | null;
    notes: string | null;
    has_receipt: boolean;
    receipt_url: string | null;
};

type Props = {
    expenses: {
        data: ExpenseRow[];
        current_page: number;
        last_page: number;
    };
    filters: { category: string };
    categories: string[];
};

export default function ExpensesIndex({
    expenses,
    filters,
    categories,
}: Props) {
    const [category, setCategory] = useState(filters.category || 'all');

    const applyFilter = (value: string) => {
        setCategory(value);
        router.get(
            expensesIndexRoute.url({
                query: { category: value === 'all' ? '' : value },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Expenses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Expenses"
                        description="Maintenance, renovation and other costs across your assets."
                    />
                    <div className="flex items-center gap-2">
                        <Select
                            value={category || 'all'}
                            onValueChange={applyFilter}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All categories
                                </SelectItem>
                                {categories.map((c) => (
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
                        <Button asChild data-test="new-expense-button">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Record expense
                            </Link>
                        </Button>
                    </div>
                </div>

                {expenses.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No expenses recorded yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-2">Asset</th>
                                    <th className="px-3 py-2">Unit</th>
                                    <th className="px-3 py-2">Category</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2 text-right">
                                        Receipt
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Edit
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.data.map((expense) => (
                                    <tr
                                        key={expense.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {expense.asset_name}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {expense.unit_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant="secondary"
                                                className="capitalize"
                                            >
                                                {expense.category}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {expense.amount} {expense.currency}
                                        </td>
                                        <td className="px-3 py-2">
                                            {expense.spent_on}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {expense.receipt_url ? (
                                                <a
                                                    href={expense.receipt_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex text-primary"
                                                    aria-label="View receipt"
                                                >
                                                    <Receipt className="size-4" />
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={edit({
                                                        expense: expense.id,
                                                    })}
                                                    aria-label={`Edit expense for ${expense.asset_name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {expenses.last_page > 1 && (
                    <div className="flex gap-1 text-sm">
                        {Array.from(
                            { length: expenses.last_page },
                            (_, i) => i + 1,
                        ).map((page) => (
                            <Button
                                key={page}
                                variant={
                                    page === expenses.current_page
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        expensesIndexRoute.url({
                                            query: {
                                                page,
                                                category:
                                                    category === 'all'
                                                        ? ''
                                                        : category,
                                            },
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

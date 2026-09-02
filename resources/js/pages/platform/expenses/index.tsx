import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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

type ExpenseRow = {
    id: number;
    category: string;
    amount: number;
    currency: string;
    spent_on: string;
    vendor_name: string | null;
    notes: string | null;
};

type Props = {
    expenses: {
        data: ExpenseRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        category: string;
    };
};

export default function PlatformExpensesIndex({ expenses, filters }: Props) {
    const authPermissions = (usePage().props.auth?.permissions ??
        []) as string[];
    const canCreate = authPermissions.includes('platform-expense.create');
    const canDelete = authPermissions.includes('platform-expense.delete');
    const [deleteTarget, setDeleteTarget] = useState<ExpenseRow | null>(null);

    const form = useForm({
        search: filters.search,
        category: filters.category,
    });

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            expenseRoutes.index().url,
            { search: form.data.search, category: form.data.category },
            { preserveState: true, replace: true },
        );
    }

    function setCategory(category: string) {
        form.setData('category', category);
        router.get(
            expenseRoutes.index().url,
            { search: form.data.search, category },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Platform expenses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Platform expenses"
                        description="Platform-level operating costs, tracked separately from asset expenses."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={expenseRoutes.create()}>
                                <Plus className="size-4" />
                                New expense
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="p-3">
                        <Form onSubmit={submitSearch} className="flex gap-2">
                            {({ processing }) => (
                                <>
                                    <div className="relative flex-1">
                                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            name="search"
                                            value={form.data.search}
                                            onChange={(e) =>
                                                form.setData(
                                                    'search',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Search vendor, category or notes"
                                            className="pl-8"
                                        />
                                    </div>
                                    <Select
                                        value={form.data.category}
                                        onValueChange={setCategory}
                                    >
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">
                                                All categories
                                            </SelectItem>
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
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Search
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">
                                            Category
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Vendor
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Amount
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Spent on
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Notes
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {expenses.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No platform expenses found.
                                            </td>
                                        </tr>
                                    )}
                                    {expenses.data.map((expense) => (
                                        <tr
                                            key={expense.id}
                                            className="border-b"
                                        >
                                            <td className="px-3 py-2">
                                                <Badge variant="secondary">
                                                    {expense.category}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {expense.vendor_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 font-medium">
                                                {expense.currency}{' '}
                                                {Number(
                                                    expense.amount,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="px-3 py-2">
                                                {expense.spent_on}
                                            </td>
                                            <td className="max-w-xs truncate px-3 py-2 text-muted-foreground">
                                                {expense.notes ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={expenseRoutes.edit(
                                                                expense.id,
                                                            )}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    expense,
                                                                )
                                                            }
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {expenses.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {expenses.current_page} of {expenses.last_page}{' '}
                            ({expenses.total} expenses)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={expenses.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        expenseRoutes.index().url,
                                        {
                                            search: filters.search,
                                            category: filters.category,
                                            page: expenses.current_page - 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    expenses.current_page >= expenses.last_page
                                }
                                onClick={() =>
                                    router.get(
                                        expenseRoutes.index().url,
                                        {
                                            search: filters.search,
                                            category: filters.category,
                                            page: expenses.current_page + 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <PasswordConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                formAction={expenseRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.category ?? ''} expense?`}
                description="This soft-deletes the platform expense record. Please enter your password to confirm."
            />
        </>
    );
}

PlatformExpensesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Platform expenses', href: expenseRoutes.index().url },
    ],
};

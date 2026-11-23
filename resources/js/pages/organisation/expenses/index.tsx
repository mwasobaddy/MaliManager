import { Head, Link, router, usePage } from '@inertiajs/react';
import { SquarePen, Plus, Receipt, SearchX, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import Heading from '@/components/heading';
import { Empty } from '@/components/ui/empty';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format-date';
import { formatMoney } from '@/lib/format-money';
import {
    create,
    destroy as destroyExpense,
    edit,
    index as expensesIndexRoute,
} from '@/routes/tenant/expenses';
import { bulk as trashBulk } from '@/routes/tenant/trash';

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

    const { context } = usePage().props;
    const canDelete = (context?.permissions ?? []).includes('expense.manage');

    const [deleteTarget, setDeleteTarget] = useState<ExpenseRow | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    // The delete overlay is local state, so it closes itself once the
    // fresh props come back without the row. A failed confirm (wrong
    // password) keeps the row — and the overlay — put.
    useEffect(() => {
        if (
            deleteTarget !== null &&
            !expenses.data.some((expense) => expense.id === deleteTarget.id)
        ) {
            setDeleteTarget(null);
        }
    }, [expenses.data, deleteTarget]);

    // Selection never outlives the page: a refetch that drops a row also
    // drops its tick, so the bulk payload can only name visible rows.
    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                expenses.data.some((expense) => expense.id === id),
            ),
        );
    }, [expenses.data]);

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
                        level="h1"
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
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Record expense"
                            tooltip="Record an expense against a property"
                            color="primary"
                            href={create().url}
                            data-test="new-expense-button"
                            className="max-w-20 sm:max-w-24"
                        />
                    </div>
                </div>

                <BulkDeleteBar
                    pageIds={expenses.data.map((expense) => expense.id)}
                    selectedIds={selectedIds}
                    onSelectionChange={setSelectedIds}
                    onDelete={() => setBulkOpen(true)}
                    onClear={() => setSelectedIds([])}
                    canDelete={canDelete}
                    noun="expenses"
                />

                {expenses.data.length === 0 ? (
                    <Empty
                        icon={<Receipt className="size-4" />}
                        title="No expenses recorded yet"
                        description="Maintenance, renovation and other costs will appear here."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    {canDelete && <th className="w-8" />}
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
                                        {canDelete && (
                                            <td className="px-3 py-2">
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        expense.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedIds(
                                                            (current) =>
                                                                checked === true
                                                                    ? [
                                                                          ...current,
                                                                          expense.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              expense.id,
                                                                      ),
                                                        )
                                                    }
                                                    aria-label={`Select expense ${expense.asset_name ?? `expense ${expense.id}`}`}
                                                />
                                            </td>
                                        )}
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
                                            {formatMoney(
                                                expense.amount,
                                                expense.currency,
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {expense.spent_on
                                                ? formatDate(expense.spent_on)
                                                : '—'}
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
                                            <div className="flex justify-end gap-1">
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
                                                        <SquarePen className="size-4" />
                                                    </Link>
                                                </Button>
                                                {canDelete && (
                                                    <ActionIconButton
                                                        icon={<Trash2 />}
                                                        label={`Delete expense for ${expense.asset_name ?? `expense ${expense.id}`}`}
                                                        color="danger"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                expense,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination
                    currentPage={expenses.current_page}
                    lastPage={expenses.last_page}
                    label="expenses"
                    onChange={(page) =>
                        router.get(
                            expensesIndexRoute.url({
                                query: {
                                    page,
                                    category:
                                        category === 'all' ? '' : category,
                                },
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                />

                {canDelete && deleteTarget !== null && (
                    <ConfirmDeleteOverlay
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setDeleteTarget(null);
                            }
                        }}
                        action={destroyExpense(deleteTarget.id).url}
                        title={`Delete expense for ${deleteTarget.asset_name ?? `expense ${deleteTarget.id}`}?`}
                        description="This expense moves to trash, where it can be restored or permanently deleted."
                    />
                )}

                {canDelete && bulkOpen && selectedIds.length > 0 && (
                    <ConfirmDeleteOverlay
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setBulkOpen(false);
                            }
                        }}
                        action={trashBulk().url}
                        payload={{ module: 'expenses', ids: selectedIds }}
                        title={
                            selectedIds.length === 1
                                ? 'Delete this expense?'
                                : `Delete ${selectedIds.length} expenses?`
                        }
                        description="They move to trash, where they can be restored or permanently deleted."
                        submitLabel={
                            selectedIds.length === 1
                                ? 'Delete expense'
                                : `Delete ${selectedIds.length} expenses`
                        }
                        processingLabel="Deleting…"
                        onSuccess={() => {
                            setSelectedIds([]);
                            setBulkOpen(false);
                        }}
                    />
                )}
            </div>
        </>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses' }],
};

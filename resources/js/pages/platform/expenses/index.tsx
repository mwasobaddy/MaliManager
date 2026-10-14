import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    ListFilter,
    Pencil,
    Plus,
    Receipt,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as expenseRoutes from '@/routes/platform/expenses';

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
    filters: { search: string; category: string; from: string; to: string };
    sort: { column: string | null; direction: 'asc' | 'desc' };
    /** The row the URL points at, when it is not on the current page. */
    focusedExpense?: ExpenseRow | null;
    /** The closed set the `category` rule validates against. */
    categories: string[];
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

const CATEGORY_LABELS: Record<string, string> = {
    hosting: 'Hosting',
    domain: 'Domain',
    tooling: 'Tooling',
    software: 'Software',
    marketing: 'Marketing',
    other: 'Other',
};

export default function PlatformExpensesIndex({
    expenses,
    filters,
    sort,
    focusedExpense,
    categories,
}: Props) {
    const page = usePage();
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const canCreate = permissions.includes('platform-expense.create');
    const canEdit = permissions.includes('platform-expense.edit');
    const canDelete = permissions.includes('platform-expense.delete');

    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const [isTableLoading, setIsTableLoading] = useState(false);

    // Read query params off Inertia's page.url, NOT window.location. Inertia
    // pushes history *after* React renders, so window.location is stale during
    // render and nothing re-renders afterwards — the overlay would not open
    // until a full page load. page.url is part of the reactive page object.
    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);

    const viewedId = Number(query.get('expense'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    // Prefer the row already on this page; fall back to the one the server
    // fetched for the URL. That fallback is what makes a shared link to a row
    // on another page work, and is why this overlay does not need its own page.
    const viewedExpense = isViewOpen
        ? (expenses.data.find((expense) => expense.id === viewedId) ??
          focusedExpense)
        : undefined;

    // One row sheet with switchable content rather than a sheet per action:
    // stacking Radix modals fights over the focus trap and double-dims the page.
    //
    // `confirm-delete` is a fourth mode rather than a separate second overlay:
    // the first Delete press only asks whether you mean it, and the password
    // prompt appears inside the same sheet once you have said yes.
    type RowMode = 'view' | 'edit' | 'delete' | 'confirm-delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isCreateOpen = query.get('create') === '1';

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    function currentFilters(): Record<string, string> {
        return {
            search: filters.search,
            category: filters.category,
            from: filters.from,
            to: filters.to,
        };
    }

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(expenseRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['expenses', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(expenseRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['expenses', 'filters', 'sort'],
            onFinish: () => setIsTableLoading(false),
        });
    }

    // Overlays push history so Back closes them; closing replaces so Back does
    // not walk back through every sheet the user opened.
    function setOverlayParam(
        key: string,
        value: string | null,
        options?: {
            replace?: boolean;
            keep?: Record<string, string>;
        },
    ) {
        navigate(
            {
                ...currentFilters(),
                sort: sort.column ?? '',
                direction: sort.direction,
                ...(options?.keep ?? {}),
                [key]: value ?? '',
            },
            { replace: options?.replace },
        );
    }

    /** Close the row sheet, dropping both the row id and the mode. */
    function closeRowSheet() {
        navigate(
            {
                ...currentFilters(),
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    /**
     * Opens the row overlay in `mode`. Every mode is available on every
     * viewport — on a phone the overlay is a bottom drawer rather than a side
     * sheet, but nothing has to be reached by redirecting to a separate page.
     */
    function openRow(expense: ExpenseRow, mode: RowMode) {
        setOverlayParam('mode', mode, {
            replace: mode === 'view',
            keep: { expense: String(expense.id) },
        });
    }

    function changePage(nextPage: number) {
        refetchTable({
            ...currentFilters(),
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];
        refetchTable(
            {
                ...currentFilters(),
                sort: first?.id ?? '',
                direction: first?.desc ? 'desc' : 'asc',
            },
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        // Never fire on mount — the server already sent the filtered result.
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === filters.search) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetchTable(
                {
                    ...currentFilters(),
                    search: searchDraft,
                    sort: sort.column ?? '',
                    direction: sort.direction,
                },
                { replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    // Keep the box in step with Back/Forward and with the filters overlay
    // clearing the search. Adjusting during render (rather than in an effect)
    // is React's documented pattern for derived-from-props state, and avoids
    // the extra render pass an effect would cost.
    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const columns = useMemo<ColumnDef<ExpenseRow, unknown>[]>(() => {
        const cells: ColumnDef<ExpenseRow, unknown>[] = [
            {
                id: 'spent_on',
                accessorKey: 'spent_on',
                header: 'Spent on',
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap tabular-nums">
                        {row.original.spent_on}
                    </span>
                ),
            },
            {
                // Six fixed values and the one the `category` rule validates
                // against, so it is a closed set and suits a Select in a dense
                // row — the filters overlay offers the same set as a radio group,
                // where there is room to see every option at once.
                id: 'category',
                accessorKey: 'category',
                header: 'Category',
                cell: ({ row }) => (
                    <span className="text-xs capitalize">
                        {CATEGORY_LABELS[row.original.category] ??
                            row.original.category}
                    </span>
                ),
            },
            {
                id: 'amount',
                accessorKey: 'amount',
                header: 'Amount',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap text-right font-medium tabular-nums">
                        {row.original.currency}{' '}
                        {row.original.amount.toLocaleString()}
                    </span>
                ),
            },
            {
                id: 'vendor_name',
                accessorKey: 'vendor_name',
                header: 'Vendor',
                cell: ({ row }) =>
                    row.original.vendor_name ? (
                        <span
                            title={row.original.vendor_name}
                            className="block max-w-48 truncate text-sm"
                        >
                            {row.original.vendor_name}
                        </span>
                    ) : (
                        <span className="text-xs">—</span>
                    ),
            },
            {
                // Free text with no useful ordering — a sort header here would be
                // a lie. The tooltip carries the whole note.
                id: 'notes',
                accessorKey: 'notes',
                header: 'Notes',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.notes ? (
                        <span
                            title={row.original.notes}
                            className="block max-w-64 truncate text-xs sm:max-w-96"
                        >
                            {row.original.notes}
                        </span>
                    ) : (
                        <span className="text-xs">—</span>
                    ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const expense = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            // The whole row opens the detail sheet; interactive
                            // controls inside it must not also trigger that.
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canEdit && (
                                <ActionIconButton
                                    icon={<Pencil />}
                                    label={`Edit expense from ${
                                        expense.vendor_name ??
                                        CATEGORY_LABELS[expense.category] ??
                                        expense.category
                                    }`}
                                    color="edit"
                                    onClick={() => openRow(expense, 'edit')}
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete expense from ${
                                        expense.vendor_name ??
                                        CATEGORY_LABELS[expense.category] ??
                                        expense.category
                                    }`}
                                    color="danger"
                                    onClick={() => openRow(expense, 'delete')}
                                />
                            )}
                        </div>
                    );
                },
            },
        ];

        return cells;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canEdit, canDelete, filters.search, filters.category, filters.from, filters.to, sort.column, sort.direction]);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    return (
        <>
            <Head title="Platform expenses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Platform expenses"
                        description="Operating costs tracked separately from tenant asset expenses."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New expense"
                            tooltip="Record a platform expense"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). "New expense" is a page-level CTA
                 * and stays in the page header.
                 *
                 * `gap-0 py-0` is what makes the band flush with the card edge:
                 * the Card's own padding would otherwise inset the search row
                 * relative to the table below it.
                 */}
                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search category, vendor, notes..."
                                aria-label="Search platform expenses"
                                // shadcn Input is bg-transparent, which leaves
                                // the field defined by a 1.2:1 border alone. On
                                // a card surface it needs a fill + a stronger
                                // border to be readable in both schemes.
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', null, {
                                          replace: true,
                                      })
                                    : setOverlayParam('filters', '1')
                            }
                        />
                    </div>
                    <DataTable
                        columns={columns}
                        data={expenses.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        loadingRows={15}
                        onRowClick={(expense) => openRow(expense, 'view')}
                        empty="No expenses match your filters."
                    />
                </Card>

                {expenses.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {expenses.current_page} of {expenses.last_page} (
                            {expenses.total} expenses)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={expenses.current_page <= 1}
                                onClick={() =>
                                    changePage(expenses.current_page - 1)
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
                                    changePage(expenses.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* The row sheet: view, edit, delete and confirm share one overlay. */}
            <ResponsiveOverlay
                open={isViewOpen && viewedExpense !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={
                    viewedExpense
                        ? `${viewedExpense.currency} ${viewedExpense.amount.toLocaleString()}`
                        : 'Expense'
                }
                description={
                    !viewedExpense
                        ? 'This expense is not on the current page of results.'
                        : rowMode === 'delete'
                          ? 'This soft-deletes the record. The audit trail is kept.'
                          : rowMode === 'confirm-delete'
                            ? 'Confirm with your password.'
                            : rowMode === 'edit'
                              ? 'Update the amount, category or vendor.'
                              : 'Details for this expense.'
                }
                footer={
                    rowMode === 'delete' && viewedExpense ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Continue to delete',
                                    tone: 'danger-ghost',
                                    icon: <Trash2 className="size-4" />,
                                    onClick: () =>
                                        setOverlayParam('mode', 'confirm-delete', {
                                            keep: {
                                                expense: String(
                                                    viewedExpense.id,
                                                ),
                                            },
                                        }),
                                },
                            ]}
                        />
                    ) : rowMode === 'confirm-delete' && viewedExpense ? (
                        <OverlayFooter
                            onCancel={() => openRow(viewedExpense, 'delete')}
                            actions={[
                                {
                                    label: 'Delete expense',
                                    form: 'expense-delete-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'edit' && viewedExpense ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'expense-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : (
                        <OverlayFooter
                            actions={[
                                {
                                    label: 'Edit',
                                    tone: 'secondary',
                                    icon: <Pencil className="size-4" />,
                                    onClick: () =>
                                        viewedExpense &&
                                        openRow(viewedExpense, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    )
                }
            >
                {viewedExpense &&
                    (rowMode === 'edit' ? (
                        <Form
                            key={viewedExpense.id}
                            id="expense-edit-form"
                            method="patch"
                            action={expenseRoutes.update(viewedExpense.id)}
                            onSuccess={() => closeRowSheet()}
                        >
                            <ExpenseFormFields
                                expense={viewedExpense}
                                categories={categories}
                                errors={errors}
                            />
                        </Form>
                    ) : rowMode === 'delete' ? (
                        <p className="text-sm">
                            Deleting this expense removes{' '}
                            <span className="font-medium">
                                {viewedExpense.currency}{' '}
                                {viewedExpense.amount.toLocaleString()}
                            </span>{' '}
                            spent on {viewedExpense.spent_on}
                            {viewedExpense.vendor_name ? (
                                <>
                                    {' '}
                                    on{' '}
                                    <span className="font-medium">
                                        {viewedExpense.vendor_name}
                                    </span>
                                </>
                            ) : (
                                ''
                            )}
                            . It drops out of the totals. The record is kept, so
                            it can be restored.
                        </p>
                    ) : rowMode === 'confirm-delete' ? (
                        <Form
                            id="expense-delete-form"
                            method="delete"
                            action={expenseRoutes.destroy(viewedExpense.id)}
                            onSuccess={() => closeRowSheet()}
                            className="grid gap-3"
                        >
                            <p className="text-sm">
                                This soft-deletes the expense. Confirm with your
                                password to continue.
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="delete-password">
                                    Confirm with your password
                                </Label>
                                <Input
                                    id="delete-password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                        </Form>
                    ) : (
                        <ExpenseDetail expense={viewedExpense} />
                    ))}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Record an expense"
                description="A platform-level operating cost — hosting, domains, tooling."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Record expense',
                                form: 'expense-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="expense-create-form"
                    method="post"
                    action={expenseRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                >
                    <ExpenseFormFields
                        categories={categories}
                        errors={errors}
                    />
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of expenses."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                tone: 'secondary',
                                onClick: () =>
                                    refetchTable(
                                        {
                                            search: '',
                                            category: '',
                                            from: '',
                                            to: '',
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                        },
                                        { replace: true },
                                    ),
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        {/* A fixed set of six that the `category` rule validates
                            against, so a radio group is honest here — and with
                            this much room every option is visible at once,
                            which a dropdown would only add a tap to. */}
                        <RadioGroup
                            value={filters.category || 'all'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        ...currentFilters(),
                                        category: value === 'all' ? '' : value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                            className="grid gap-2"
                        >
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    id="filter-category-all"
                                    value="all"
                                />
                                <Label
                                    htmlFor="filter-category-all"
                                    className="cursor-pointer"
                                >
                                    Any category
                                </Label>
                            </div>
                            {categories.map((category) => (
                                <div
                                    key={category}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`filter-category-${category}`}
                                        value={category}
                                    />
                                    <Label
                                        htmlFor={`filter-category-${category}`}
                                        className="cursor-pointer capitalize"
                                    >
                                        {CATEGORY_LABELS[category] ??
                                            category}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </div>

                    <div className="grid gap-2">
                        <Label>Spent between</Label>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-from"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    From
                                </Label>
                                <Input
                                    id="filter-from"
                                    type="date"
                                    value={filters.from}
                                    onChange={(event) =>
                                        refetchTable(
                                            {
                                                ...currentFilters(),
                                                from: event.target.value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-to"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    To
                                </Label>
                                <Input
                                    id="filter-to"
                                    type="date"
                                    value={filters.to}
                                    onChange={(event) =>
                                        refetchTable(
                                            {
                                                ...currentFilters(),
                                                to: event.target.value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-search">Search</Label>
                        <Input
                            id="filter-search"
                            value={searchDraft}
                            onChange={(event) =>
                                setSearchDraft(event.target.value)
                            }
                            placeholder="Category, vendor, notes..."
                        />
                        {errors.search && (
                            <p className="text-xs text-destructive">
                                {errors.search}
                            </p>
                        )}
                    </div>
                </div>
            </ResponsiveOverlay>
        </>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

/** Read-only summary of an expense, for the row sheet's default mode. */
function ExpenseDetail({ expense }: { expense: ExpenseRow }) {
    return (
        <div className="grid gap-4 py-2">
            <div className="flex items-center gap-3">
                <span className="text-muted-foreground flex items-center gap-2 text-sm">
                    <Receipt className="size-4" />
                    {expense.spent_on}
                </span>
                <span className="text-lg font-semibold tabular-nums">
                    {expense.currency} {expense.amount.toLocaleString()}
                </span>
            </div>

            <dl className="grid gap-3 text-sm">
                {[
                    [
                        'Category',
                        CATEGORY_LABELS[expense.category] ?? expense.category,
                    ],
                    ['Vendor', expense.vendor_name ?? '—'],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="grid grid-cols-[9rem_1fr] gap-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="min-w-0 break-words">{value}</dd>
                    </div>
                ))}
            </dl>

            {expense.notes && (
                <div className="grid gap-2">
                    <div className="text-sm font-medium">Notes</div>
                    <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                        {expense.notes}
                    </p>
                </div>
            )}
        </div>
    );
}

/**
 * The create and edit forms share one component.
 *
 * `category` is a closed set, but in a two-column row a Select reads better
 * than six stacked radios, so the picker is a Select and the hidden `""` input
 * beside it is what submits "nothing chosen" — Radix reserves the empty value
 * for its placeholder.
 */
function ExpenseFormFields({
    expense,
    categories,
    errors,
}: {
    expense?: ExpenseRow;
    categories: string[];
    errors: Record<string, string>;
}) {
    const [category, setCategory] = useState(expense?.category ?? '');

    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className="grid gap-3 py-2">
            <div className="grid gap-2">
                <Label htmlFor="expense-amount">Amount</Label>
                <div className="grid grid-cols-[1fr_6rem] gap-3">
                    <Input
                        id="expense-amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min={0}
                        defaultValue={String(expense?.amount ?? '')}
                        required
                    />
                    <Input
                        name="currency"
                        maxLength={3}
                        defaultValue={expense?.currency ?? 'KES'}
                        aria-label="Currency"
                        required
                    />
                </div>
                <FieldError message={errors.amount} />
                <FieldError message={errors.currency} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="expense-spent_on">Spent on</Label>
                <Input
                    id="expense-spent_on"
                    name="spent_on"
                    type="date"
                    defaultValue={expense?.spent_on ?? today}
                    required
                />
                <FieldError message={errors.spent_on} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="expense-category">Category</Label>
                    <Select value={category} onValueChange={setCategory}>
                        <SelectTrigger id="expense-category">
                            <SelectValue placeholder="Choose a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((option) => (
                                <SelectItem key={option} value={option}>
                                    <span className="capitalize">
                                        {CATEGORY_LABELS[option] ?? option}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="category"
                        value={category}
                        readOnly
                    />
                    <FieldError message={errors.category} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="expense-vendor_name">Vendor</Label>
                    <Input
                        id="expense-vendor_name"
                        name="vendor_name"
                        defaultValue={expense?.vendor_name ?? ''}
                    />
                    <FieldError message={errors.vendor_name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="expense-notes">Notes</Label>
                <Textarea
                    id="expense-notes"
                    name="notes"
                    rows={3}
                    defaultValue={expense?.notes ?? ''}
                />
                <FieldError message={errors.notes} />
            </div>
        </div>
    );
}

PlatformExpensesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Platform expenses', href: expenseRoutes.index().url },
    ],
};

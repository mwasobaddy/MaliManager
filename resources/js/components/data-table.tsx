import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type {
    ColumnDef,
    RowData,
    SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useLayoutEffect, useRef, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

declare module '@tanstack/react-table' {
    // Column-level alignment. TanStack's ColumnMeta is empty by default, so it
    // has to be augmented for `meta.align` to type-check. The generics are
    // required by the interface but unused here.
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        align?: 'left' | 'right' | 'center';
    }
}

type DataTableProps<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    getRowId?: (row: TData, index: number) => string;
    sorting?: SortingState;
    onSortingChange?: (sorting: SortingState) => void;
    /**
     * Called when a row is activated. Interactive children (buttons, links)
     * should call `event.stopPropagation()` so they do not double-fire.
     */
    onRowClick?: (row: TData) => void;
    /** Server-side pagination; the table itself does not page. */
    empty?: ReactNode;
    /**
     * Swaps the body for skeleton rows while a server-side change is in
     * flight. The header stays put so the table does not jump, and the
     * skeleton keeps the row height of the real rows.
     */
    loading?: boolean;
    /**
     * How many skeleton rows to draw. Defaults to the visible row count, but
     * never fewer than 5: filtering from 20 rows down to 1 would otherwise
     * collapse the table to a single grey line and jump twice.
     */
    loadingRows?: number;
    className?: string;
};

/**
 * Shared table primitive for admin/tenant lists.
 *
 * Sorting is **server-side**: the table is told the current sort via the
 * `sorting` prop and reports a new one through `onSortingChange`. It renders
 * `page.getAllRowModel()`, so it must not be given the whole dataset —
 * paginate server-side and pass the current page only. That keeps a 25k-row
 * table as cheap as a 25-row one.
 *
 * Header styling follows `ui-design-system.md` §4.2 Data Table: uppercase 12px
 * muted labels, `--border-strong` rule, `--brand-primary` active sort icon.
 */
export function DataTable<TData>({
    columns,
    data,
    getRowId,
    sorting = [],
    onSortingChange,
    onRowClick,
    empty = 'Nothing to show.',
    loading = false,
    loadingRows,
    className,
}: DataTableProps<TData>) {
    const table = useReactTable({
        data,
        columns,
        getRowId,
        state: { sorting },
        // Manual sorting: we never sort locally, we ask the server.
        manualSorting: true,
        onSortingChange: (updater) => {
            const next =
                typeof updater === 'function' ? updater(sorting) : updater;
            onSortingChange?.(next);
        },
        getCoreRowModel: getCoreRowModel(),
    });

    // Skeleton rows have to be exactly as tall as real ones, and that height is
    // content-dependent — a two-line name cell makes a 53px row, a single-line
    // one makes a 36px row — so guessing a constant looks wrong on at least one
    // table. Measure a real row whenever we have one, then reuse it for the
    // skeleton. Left unset on the very first paint (no rows yet), where the
    // two-bar fallback in the skeleton cell keeps the shape roughly right.
    const tbodyRef = useRef<HTMLTableSectionElement>(null);
    const [rowHeight, setRowHeight] = useState<number | null>(null);

    useLayoutEffect(() => {
        if (loading) {
            return;
        }

        const measured =
            tbodyRef.current?.querySelector('tr')?.getBoundingClientRect()
                .height ?? null;

        if (measured && measured !== rowHeight) {
            setRowHeight(measured);
        }
    }, [loading, data, rowHeight]);

    return (
        <div className="overflow-x-auto">
            <table className={cn('w-full border-collapse', className)}>
                <thead>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <tr
                            key={headerGroup.id}
                            className="bg-muted/40 border-b border-border"
                        >
                            {headerGroup.headers.map((header) => {
                                const sortable =
                                    header.column.columnDef.enableSorting !==
                                    false &&
                                    header.column.getCanSort();

                                const sorted = header.column.getIsSorted();

                                // Numeric columns read better right-aligned;
                                // status stays centred.
                                const align =
                                    header.column.columnDef.meta?.align ??
                                    'left';

                                const alignClass =
                                    align === 'right'
                                        ? 'text-right'
                                        : align === 'center'
                                          ? 'text-center'
                                          : 'text-left';

                                return (
                                    <th
                                        key={header.id}
                                        scope="col"
                                        aria-sort={
                                            sorted === 'asc'
                                                ? 'ascending'
                                                : sorted === 'desc'
                                                  ? 'descending'
                                                  : 'none'
                                        }
                                        className={cn(
                                            'text-muted-foreground px-3 py-2 text-xs font-medium tracking-wide uppercase',
                                            alignClass,
                                        )}
                                    >
                                        {header.isPlaceholder ? null : sortable ? (
                                            <button
                                                type="button"
                                                onClick={header.column.getToggleSortingHandler()}
                                                className="hover:text-foreground focus-visible:ring-ring inline-flex items-center gap-1.5 rounded transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
                                            >
                                                {flexRender(
                                                    header.column.columnDef.header,
                                                    header.getContext(),
                                                )}
                                                {sorted === 'asc' ? (
                                                    /* §5: the active sort is
                                                       wayfinding, so accent —
                                                       not the terracotta that
                                                       means "you did this". */
                                                    <ArrowUp className="text-accent-text size-3.5" />
                                                ) : sorted === 'desc' ? (
                                                    <ArrowDown className="text-accent-text size-3.5" />
                                                ) : (
                                                    <ChevronsUpDown className="size-3.5 opacity-40" />
                                                )}
                                            </button>
                                        ) : (
                                            flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    ))}
                </thead>
                <tbody ref={tbodyRef}>
                    {loading ? (
                        Array.from({
                            length: loadingRows ?? Math.max(data.length, 5),
                        }).map((_, rowIndex) => (
                            <tr
                                key={`skeleton-${rowIndex}`}
                                className="border-border/60 border-b last:border-b-0"
                                // Pin the row to the measured height of a real
                                // row. Without it the single 4px bar leaves a
                                // ~32px row against a 53px real one, which
                                // reads as "thin" rather than "loading".
                                style={
                                    rowHeight
                                        ? { height: `${rowHeight}px` }
                                        : undefined
                                }
                                aria-hidden="true"
                            >
                                {columns.map((column, cellIndex) => {
                                    const align =
                                        column.meta?.align ?? 'left';

                                    return (
                                        <td
                                            key={`skeleton-${rowIndex}-${cellIndex}`}
                                            className={cn(
                                                'px-3 py-2',
                                                align === 'right'
                                                    ? 'text-right'
                                                    : align === 'center'
                                                      ? 'text-center'
                                                      : 'text-left',
                                            )}
                                        >
                                            {cellIndex === 0 ? (
                                                // Two bars, mirroring the
                                                // primary column's title +
                                                // subtitle, so the fallback
                                                // shape is right before the
                                                // first measurement lands.
                                                <span className="flex flex-col gap-1.5">
                                                    <Skeleton className="h-4 w-3/5" />
                                                    <Skeleton className="h-3 w-2/5" />
                                                </span>
                                            ) : (
                                                <Skeleton
                                                    className={cn(
                                                        'h-4',
                                                        cellIndex === 1
                                                            ? 'w-2/5'
                                                            : 'w-12',
                                                    )}
                                                />
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))
                    ) : table.getRowModel().rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="text-muted-foreground px-3 py-10 text-center text-sm"
                            >
                                {empty}
                            </td>
                        </tr>
                    ) : (
                        table.getRowModel().rows.map((row) => (
                            <tr
                                key={row.id}
                                onClick={
                                    onRowClick
                                        ? () => onRowClick(row.original)
                                        : undefined
                                }
                                className={cn(
                                    'hover:bg-brand-primary-100/40 border-border/60 border-b transition-colors last:border-b-0',
                                    onRowClick &&
                                        'cursor-pointer focus-visible:bg-brand-primary-100/40 focus-visible:ring-ring focus-visible:ring-[3px] focus-visible:outline-none',
                                )}
                            >
                                {row.getVisibleCells().map((cell) => {
                                    const cellAlign =
                                        cell.column.columnDef.meta?.align ??
                                        'left';

                                    return (
                                    <td
                                        key={cell.id}
                                        className={cn(
                                            'px-3 py-2 text-sm',
                                            cellAlign === 'right'
                                                ? 'text-right'
                                                : cellAlign === 'center'
                                                  ? 'text-center'
                                                  : 'text-left',
                                        )}
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </td>
                                    );
                                })}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
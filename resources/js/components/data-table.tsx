import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DataTableProps<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    getRowId?: (row: TData, index: number) => string;
    sorting?: SortingState;
    onSortingChange?: (sorting: SortingState) => void;
    /** Server-side pagination; the table itself does not page. */
    empty?: ReactNode;
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
    empty = 'Nothing to show.',
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
                                        className="text-muted-foreground px-3 py-2 text-left text-xs font-medium tracking-wide uppercase"
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
                                                    <ArrowUp className="text-brand-primary size-3.5" />
                                                ) : sorted === 'desc' ? (
                                                    <ArrowDown className="text-brand-primary size-3.5" />
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
                <tbody>
                    {table.getRowModel().rows.length === 0 ? (
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
                                className="hover:bg-brand-primary-100/40 border-border/60 border-b transition-colors last:border-b-0"
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <td key={cell.id} className="px-3 py-2 text-sm">
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
import { useState } from 'react';
import { Download } from 'lucide-react';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type ExportSheetProps = {
    open: boolean;
    onClose: () => void;
    /** `canExport` from the page's permission list. */
    canExport: boolean;
    /** `users.total`-style count, for the "ignores pagination" line. */
    total: number;
    /** Rows on the page the visitor is looking at right now. */
    pageCount: number;
    currentPage: number;
    /** Human description of the active filters, or null when none are set. */
    activeFilters: string | null;
    /** Every column the backend can emit, in download order. */
    columns: string[];
    /** Downloads the file and closes the sheet. */
    onExport: (scope: 'all' | 'page', columns: string[]) => void;
};

/**
 * What-rows plus which-columns chooser for a CSV export.
 *
 * It exists because "Export" on a filtered table is ambiguous twice over:
 * the user cannot tell whether they are getting the rows they can see or
 * everything behind them, nor which columns will be in the file. One scope
 * radio plus ticked columns answers both before the download starts.
 */
export function CsvExportSheet({
    open,
    onClose,
    canExport,
    total,
    pageCount,
    currentPage,
    activeFilters,
    columns,
    onExport,
}: ExportSheetProps) {
    const [scope, setScope] = useState<'all' | 'page'>('all');
    const [selected, setSelected] = useState<string[]>(columns);

    if (!canExport) {
        return null;
    }

    // Reopening starts from everything ticked: a previous un-tick is a
    // decision about that download, not a preference for the next one.
    function reset(next: boolean) {
        if (next) {
            setScope('all');
            setSelected(columns);
        }

        if (!next) {
            onClose();
        }
    }

    function toggle(column: string) {
        setSelected((current) =>
            current.includes(column)
                ? current.filter((entry) => entry !== column)
                : [...current, column],
        );
    }

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={reset}
            title="Export CSV"
            description="Choose the rows and columns to include."
            footer={
                <OverlayFooter
                    onCancel={onClose}
                    actions={[
                        {
                            label: `Export${selected.length === columns.length ? '' : ` ${selected.length} of ${columns.length} columns`}`,
                            icon: <Download className="size-4" />,
                            disabled: selected.length === 0,
                            onClick: () => onExport(scope, selected),
                        },
                    ]}
                />
            }
        >
            <div className="space-y-4 py-2 text-sm">
                <fieldset className="grid gap-1">
                    <legend className="text-sm font-medium">Rows</legend>
                    {(
                        [
                            [
                                'all',
                                'All matching',
                                `Every row matching ${activeFilters ?? 'everything — no filters are active'}. Ignores pagination — ${total} row${total === 1 ? '' : 's'} in total.`,
                            ],
                            [
                                'page',
                                'Current page',
                                `Only the ${pageCount} row${pageCount === 1 ? '' : 's'} visible on page ${currentPage}.`,
                            ],
                        ] as const
                    ).map(([value, title, hint]) => (
                        <label
                            key={value}
                            className="flex cursor-pointer items-start gap-2.5 px-1 py-2"
                        >
                            <input
                                type="radio"
                                name="export-scope"
                                value={value}
                                checked={scope === value}
                                onChange={() => setScope(value)}
                                className="mt-1 accent-brand-accent"
                            />
                            <span>
                                <span className="text-sm font-medium">
                                    {title}
                                </span>
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    {hint}
                                </span>
                            </span>
                        </label>
                    ))}
                </fieldset>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        Columns{' '}
                        <span className="font-normal text-muted-foreground">
                            ({selected.length} of {columns.length})
                        </span>
                    </legend>
                    <div className="flex items-center gap-2.5 rounded-md px-1 py-1.5 hover:bg-muted/50">
                        <Checkbox
                            id="export-columns-all"
                            checked={
                                selected.length === columns.length
                                    ? true
                                    : selected.length === 0
                                      ? false
                                      : 'indeterminate'
                            }
                            onCheckedChange={() =>
                                setSelected(
                                    selected.length === columns.length
                                        ? []
                                        : [...columns],
                                )
                            }
                        />
                        <Label
                            htmlFor="export-columns-all"
                            className="flex-1 cursor-pointer text-sm font-medium"
                        >
                            {selected.length === columns.length
                                ? 'Deselect all'
                                : 'Select all'}
                        </Label>
                    </div>
                    <div className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                        {columns.map((column) => (
                            <div
                                key={column}
                                className="flex min-w-0 items-center gap-2 rounded-md px-1 py-1.5 hover:bg-muted/50"
                            >
                                <Checkbox
                                    id={`export-column-${column}`}
                                    checked={selected.includes(column)}
                                    onCheckedChange={() => toggle(column)}
                                />
                                <Label
                                    htmlFor={`export-column-${column}`}
                                    className="min-w-0 flex-1 cursor-pointer truncate text-sm font-normal"
                                    title={column}
                                >
                                    {column}
                                </Label>
                            </div>
                        ))}
                    </div>
                </fieldset>
            </div>
        </ResponsiveOverlay>
    );
}

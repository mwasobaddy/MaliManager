import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type Props = {
    /** Every selectable id on this page. */
    pageIds: number[];
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
    /** Opens the password confirmation for the bulk delete. */
    onDelete: () => void;
    onClear: () => void;
    canDelete: boolean;
    /** Plural noun for copy, e.g. 'units'. */
    noun: string;
    /**
     * Tables own their select-all in the header checkbox, so the bar
     * stays a pure action row there. Card lists have no header — they
     * opt into the page toggle here instead. One select-all per view,
     * never two.
     */
    showSelectAll?: boolean;
};

/**
 * The one bulk bar for every index table. It only exists while a
 * selection does — a permanently visible bar with a disabled Delete
 * trains the reader to ignore it, and the scope ("these N rows") is
 * only meaningful once rows are ticked.
 *
 * A full-bleed teal strip, flush against the table: no border, no
 * margin, no rounding. It reads as a mode the table is in, not another
 * card stacked on the page.
 *
 * Select-all is page-scoped everywhere. A single click never reaches
 * rows the reader cannot see.
 */
export function BulkDeleteBar({
    pageIds,
    selectedIds,
    onSelectionChange,
    onDelete,
    onClear,
    canDelete,
    noun,
    showSelectAll = false,
}: Props) {
    if (!canDelete || selectedIds.length === 0) {
        return null;
    }

    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-x-3 gap-y-2 bg-brand-accent px-4 py-2 text-sm text-on-accent"
        >
            {showSelectAll && (
                <Checkbox
                    checked={
                        allSelected
                            ? true
                            : selectedIds.length === 0
                              ? false
                              : 'indeterminate'
                    }
                    onCheckedChange={() =>
                        onSelectionChange(allSelected ? [] : [...pageIds])
                    }
                    aria-label={`Select all ${noun} on this page`}
                    className="border-white/60 data-[state=checked]:border-primary"
                />
            )}
            <span>{selectedIds.length} selected</span>
            <span className="flex-1" />
            <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={onDelete}
            >
                Delete {selectedIds.length}
            </Button>
            <Button
                type="button"
                variant="shadcndefault"
                size="sm"
                onClick={onClear}
            >
                Clear
            </Button>
        </div>
    );
}

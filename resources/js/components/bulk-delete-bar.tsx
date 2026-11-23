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
};

/**
 * The one bulk bar for every index table. Select-all is tri-state and
 * lives here — never a second one in the table header — so card lists
 * and DataTables share the exact same selection chrome.
 *
 * Hidden when there is nothing to select or the actor may not delete;
 * the table underneath already covers the empty state.
 */
export function BulkDeleteBar({
    pageIds,
    selectedIds,
    onSelectionChange,
    onDelete,
    onClear,
    canDelete,
    noun,
}: Props) {
    if (!canDelete || pageIds.length === 0) {
        return null;
    }

    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-input bg-card px-3 py-2 text-sm">
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
            />
            <span className="text-muted-foreground">
                {selectedIds.length === 0
                    ? `Select ${noun} to delete them in bulk`
                    : `${selectedIds.length} selected`}
            </span>
            <span className="flex-1" />
            <Button
                type="button"
                variant="destructive"
                size="sm"
                disabled={selectedIds.length === 0}
                onClick={onDelete}
            >
                {selectedIds.length === 0
                    ? 'Delete'
                    : `Delete ${selectedIds.length}`}
            </Button>
            {selectedIds.length > 0 && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClear}
                >
                    Clear
                </Button>
            )}
        </div>
    );
}

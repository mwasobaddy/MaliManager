import { Download } from 'lucide-react';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';

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
    /** Downloads the file and closes the sheet. */
    onExport: (scope: 'all' | 'page') => void;
};

/**
 * The "all matching vs this page" chooser for a CSV export.
 *
 * It exists because "Export all" on a filtered table is ambiguous: the user
 * cannot tell whether they are getting the 15 rows they can see or the 4,000
 * behind them. Saying it out loud costs one click and removes the surprise.
 */
export function CsvExportSheet({
    open,
    onClose,
    canExport,
    total,
    pageCount,
    currentPage,
    activeFilters,
    onExport,
}: ExportSheetProps) {
    if (!canExport) {
        return null;
    }

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
            title="Export CSV"
            description="Choose how many rows to include."
            footer={
                <OverlayFooter
                    onCancel={onClose}
                    actions={[
                        {
                            label: 'Current page',
                            tone: 'secondary',
                            onClick: () => onExport('page'),
                        },
                        {
                            label: 'Export all',
                            icon: <Download className="size-4" />,
                            onClick: () => onExport('all'),
                        },
                    ]}
                />
            }
        >
            <div className="space-y-3 py-2 text-sm">
                <div className="rounded-lg border border-border-strong bg-background p-3">
                    <div className="font-medium">All matching</div>
                    <div className="text-muted-foreground mt-1 text-xs">
                        Every row matching{' '}
                        {activeFilters ?? 'everything — no filters are active'}.{' '}
                        Ignores pagination — {total} row
                        {total === 1 ? '' : 's'} in total.
                    </div>
                </div>

                <div className="rounded-lg border border-border-strong bg-background p-3">
                    <div className="font-medium">Current page</div>
                    <div className="text-muted-foreground mt-1 text-xs">
                        Only the {pageCount} row{pageCount === 1 ? '' : 's'}{' '}
                        visible on page {currentPage}.
                    </div>
                </div>
            </div>
        </ResponsiveOverlay>
    );
}
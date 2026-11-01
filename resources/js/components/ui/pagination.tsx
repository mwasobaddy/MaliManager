import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PaginationProps = {
    currentPage: number;
    lastPage: number;
    /** Total row count, shown in the summary. Omit to hide it. */
    total?: number;
    /** Plural noun for the summary, e.g. "organizations". */
    label?: string;
    onChange: (page: number) => void;
    className?: string;
};

/**
 * Pagination, per v2 §6.4. Ghost buttons, and the current page is the one
 * solid terracotta element in the control (`--brand-primary-600` fill with
 * `--on-primary` text).
 *
 * This existed as an identical Prev/Next block copy-pasted into fourteen
 * pages, each with its own summary text. Beyond the duplication, every copy
 * was Prev/Next only — so a user on page 3 of 40 had no way to jump, and the
 * numbers they could see were limited to the two ends.
 *
 * The page window is current ± 1 plus the first and last, with an ellipsis
 * wherever the sequence skips. That keeps the control a fixed width no matter
 * how many pages there are.
 */
export function Pagination({
    currentPage,
    lastPage,
    total,
    label,
    onChange,
    className,
}: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 text-sm',
                className,
            )}
        >
            <span className="text-muted-foreground">
                Page {currentPage} of {lastPage}
                {total !== undefined && (
                    <>
                        {' '}
                        ({total.toLocaleString()} {label ?? 'items'})
                    </>
                )}
            </span>

            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Previous page"
                    disabled={currentPage <= 1}
                    onClick={() => onChange(currentPage - 1)}
                >
                    <ChevronLeft className="size-4" aria-hidden />
                </Button>

                {pageWindow(currentPage, lastPage).map((page, index) =>
                    page === null ? (
                        <span
                            key={`gap-${index}`}
                            className="text-muted-foreground px-1"
                            aria-hidden
                        >
                            …
                        </span>
                    ) : (
                        <Button
                            key={page}
                            type="button"
                            variant="ghost"
                            size="sm"
                            className={cn(
                                'min-w-9 tabular-nums',
                                page === currentPage &&
                                    'bg-primary text-primary-foreground hover:bg-primary/90',
                            )}
                            aria-current={
                                page === currentPage ? 'page' : undefined
                            }
                            onClick={() => onChange(page)}
                        >
                            {page}
                        </Button>
                    ),
                )}

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Next page"
                    disabled={currentPage >= lastPage}
                    onClick={() => onChange(currentPage + 1)}
                >
                    <ChevronRight className="size-4" aria-hidden />
                </Button>
            </div>
        </nav>
    );
}

/**
 * First, last, current and its immediate neighbours, with `null` marking a
 * jump. Returns at most seven entries.
 */
function pageWindow(current: number, last: number): (number | null)[] {
    const pages = new Set<number>([1, last, current - 1, current, current + 1]);

    const ordered = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    const result: (number | null)[] = [];

    ordered.forEach((page, index) => {
        const previous = ordered[index - 1];

        if (previous !== undefined && page - previous > 1) {
            result.push(null);
        }

        result.push(page);
    });

    return result;
}

import { Card, CardContent } from '@/components/ui/card';

/**
 * Pulsing wireframe shown while a deferred analytics group resolves.
 * Same card shell the content will land in, so arrival shifts nothing.
 * Decorative by design: the loaded figures carry the meaning.
 */
export function SectionSkeleton({ blocks = 4 }: { blocks?: number }) {
    const widths = [92, 78, 86, 64, 88, 72, 80];

    return (
        <Card aria-hidden>
            <CardContent className="flex flex-col gap-3 pt-5">
                {Array.from({ length: blocks }, (_, index) => (
                    <div
                        key={index}
                        className="h-4 animate-pulse rounded bg-skeleton"
                        style={{
                            width: `${widths[index % widths.length]}%`,
                        }}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Chart-shaped twin of SectionSkeleton: a title-height bar plus a body
 * block at the chart's own height. Line skeletons must never stand in
 * for a chart — the shapes have nothing in common.
 */
export function ChartSkeleton({ height = 280 }: { height?: number }) {
    return (
        <Card aria-hidden>
            <CardContent className="flex flex-col gap-3 pt-5">
                <div className="h-4 w-1/3 animate-pulse rounded bg-skeleton" />
                <div
                    className="w-full animate-pulse rounded bg-skeleton"
                    style={{ height }}
                />
            </CardContent>
        </Card>
    );
}

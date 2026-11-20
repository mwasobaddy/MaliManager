import type { ReactNode } from 'react';
import Heading from '@/components/heading';

/**
 * Page header over the property line-art. The drawing is near-white, so
 * a bottom-up background scrim carries the title in both themes while
 * the artwork shows through up top. Decorative image: headings carry
 * the meaning.
 */
export function PageHero({
    title,
    description,
    meta,
    actions,
}: {
    title: string;
    description?: string;
    /** Right-aligned summary (e.g. "4 units · 25% occupied"). */
    meta?: ReactNode;
    /** Buttons rendered under the title block. */
    actions?: ReactNode;
}) {
    return (
        <div className="relative overflow-hidden rounded-2xl border">
            <img
                src="/images/property-hero.jpg"
                alt=""
                aria-hidden
                className="absolute inset-0 h-full w-full object-cover object-center"
            />
            <div
                aria-hidden
                className="absolute inset-0 bg-gradient-to-t from-background via-background/55 to-transparent"
            />
            <div className="relative flex flex-wrap items-end justify-between gap-4 px-6 pt-16 pb-5 sm:px-8">
                <div className="space-y-2">
                    <Heading
                        variant="small"
                        level="h1"
                        title={title}
                        description={description}
                    />
                    {actions}
                </div>
                {meta}
            </div>
        </div>
    );
}

import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

/**
 * The inline text link. Teal, per v2 §5 — a link is something you can go to,
 * so it belongs to accent wayfinding, not to the terracotta action palette.
 *
 * Always underlined. §9.1 is blunt about this: an un-underlined teal link
 * sits near 2.2:1 against the body text it interrupts, so without the
 * underline it is not a link at all for anyone who cannot separate those two
 * hues. Hover deepens the colour rather than removing the underline.
 *
 * Most pages use a bare `<Link>`, which picks the same colour up from the
 * base layer in `app.css`. This component exists for the cases that need the
 * underline guaranteed regardless of context — text inside a paragraph, a
 * table cell, a heading.
 */
export default function TextLink({
    className = '',
    children,
    ...props
}: Props) {
    return (
        <Link
            className={cn(
                'text-accent-text underline decoration-accent-text/35 underline-offset-4 transition-colors duration-150 ease-out hover:text-accent-text-hover hover:decoration-current!',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
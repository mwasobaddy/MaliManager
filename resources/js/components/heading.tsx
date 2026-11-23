import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    level = 'h2',
    className,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /**
     * The page's own title renders as h1; everything else stays h2. Styling
     * comes from `variant`, so the level changes the outline only.
     */
    level?: 'h1' | 'h2';
    /** Extra layout classes for the header element, e.g. `min-w-0 flex-1`
     * to share a flex row with a page action instead of claiming the
     * whole line. */
    className?: string;
}) {
    const Title = level;

    return (
        <header
            className={cn(
                variant === 'small' ? '' : 'mb-2 space-y-0.5',
                className,
            )}
        >
            <Title
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </Title>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}

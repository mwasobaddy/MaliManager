export default function Heading({
    title,
    description,
    variant = 'default',
    level = 'h2',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /**
     * The page's own title renders as h1; everything else stays h2. Styling
     * comes from `variant`, so the level changes the outline only.
     */
    level?: 'h1' | 'h2';
}) {
    const Title = level;

    return (
        <header className={variant === 'small' ? '' : 'mb-2 space-y-0.5'}>
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

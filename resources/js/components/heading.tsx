/**
 * Section or page title with an optional one-line description.
 * `small` is the in-card variant (settings sections, sub-blocks); `default` is a page title.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? 'space-y-0.5' : 'space-y-1'}>
            <h2
                className={
                    variant === 'small'
                        ? 'font-heading text-base font-bold text-foreground'
                        : 'text-2xl leading-tight font-bold tracking-tight text-foreground lg:text-[1.75rem]'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}

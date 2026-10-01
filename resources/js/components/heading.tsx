import { cn } from '@/lib/utils';

/**
 * `default` is the page title (the page's `h1`, matching PageHeader);
 * `small` is a section heading inside a page.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
    as,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /** Override the element when the page already renders its own `h1`. */
    as?: 'h1' | 'h2';
}) {
    const Tag = as ?? (variant === 'small' ? 'h2' : 'h1');

    return (
        <header className="min-w-0 space-y-1">
            <Tag
                className={
                    variant === 'small'
                        ? 'text-base font-semibold'
                        : 'text-xl font-semibold tracking-[-0.02em] [overflow-wrap:anywhere]'
                }
            >
                {title}
            </Tag>
            {description && (
                <p
                    className={cn(
                        'text-muted-foreground text-sm',
                        variant === 'default' && 'max-w-3xl leading-6',
                    )}
                >
                    {description}
                </p>
            )}
        </header>
    );
}

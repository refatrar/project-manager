import { cn } from '@/lib/utils';

/**
 * A task label. User-chosen colours are not contrast-safe as a background,
 * so the colour is a dot beside neutral text (design system §14).
 */
export function LabelChip({
    name,
    color,
    className,
}: {
    name: string;
    color: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'text-muted-foreground inline-flex max-w-full items-center gap-1.5 rounded-md border px-1.5 py-0.5 text-xs leading-4 font-medium',
                className,
            )}
        >
            <span
                className="bg-subtle-foreground size-2 shrink-0 rounded-full"
                style={color ? { backgroundColor: color } : undefined}
                aria-hidden="true"
            />
            <span className="truncate">{name}</span>
        </span>
    );
}

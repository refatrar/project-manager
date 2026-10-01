import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    /** Tighter spacing for use inside cards, columns and lists. */
    compact?: boolean;
    className?: string;
};

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    compact = false,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center text-center',
                compact
                    ? 'gap-2 px-4 py-6'
                    : 'gap-3 rounded-lg border border-dashed px-6 py-12',
                className,
            )}
        >
            {Icon ? (
                <div
                    className={cn(
                        'bg-muted text-muted-foreground flex items-center justify-center rounded-full',
                        compact ? 'size-9' : 'size-11',
                    )}
                >
                    <Icon
                        className={compact ? 'size-4' : 'size-5'}
                        aria-hidden="true"
                    />
                </div>
            ) : null}
            <div className="space-y-1">
                <p
                    className={cn(
                        'font-medium',
                        compact ? 'text-sm' : 'text-[0.9375rem]',
                    )}
                >
                    {title}
                </p>
                {description ? (
                    <p className="text-muted-foreground mx-auto max-w-sm text-sm">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? <div className="pt-1">{action}</div> : null}
        </div>
    );
}

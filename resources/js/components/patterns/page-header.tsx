import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: ReactNode;
    description?: ReactNode;
    /** Small line above the title, e.g. a project code or parent task. */
    eyebrow?: ReactNode;
    /** Inline metadata under the title, e.g. status badges. */
    meta?: ReactNode;
    /** Page-level actions, aligned right on wide screens. */
    actions?: ReactNode;
    className?: string;
};

/** The page's one `h1`, with optional context and actions. */
export function PageHeader({
    title,
    description,
    eyebrow,
    meta,
    actions,
    className,
}: Props) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0 flex-1 space-y-1">
                {eyebrow ? (
                    <div className="text-muted-foreground flex min-w-0 items-center gap-2 text-[0.8125rem]">
                        {eyebrow}
                    </div>
                ) : null}
                <h1 className="text-xl font-semibold tracking-tight [overflow-wrap:anywhere] md:text-2xl">
                    {title}
                </h1>
                {description ? (
                    <p className="text-muted-foreground max-w-3xl text-sm leading-6">
                        {description}
                    </p>
                ) : null}
                {meta ? (
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        {meta}
                    </div>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </header>
    );
}

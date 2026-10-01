import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type Props = {
    paginator: Pick<
        Paginated<unknown>,
        'current_page' | 'last_page' | 'per_page' | 'total' | 'links'
    >;
    className?: string;
};

/**
 * Pager for a Laravel paginator. Links keep the same visit options every
 * list page used before (`preserveState`), so filters survive paging.
 */
export function Pagination({ paginator, className }: Props) {
    const { current_page, last_page, per_page, total, links } = paginator;

    if (last_page <= 1) {
        return null;
    }

    const from = (current_page - 1) * per_page + 1;
    const to = Math.min(current_page * per_page, total);
    const lastIndex = links.length - 1;

    return (
        <nav
            aria-label="Pagination"
            className={cn(
                'flex flex-col items-center justify-between gap-3 sm:flex-row',
                className,
            )}
        >
            <p className="text-muted-foreground text-[0.8125rem] tabular-nums">
                Showing{' '}
                <span className="text-foreground font-medium">{from}</span>–
                <span className="text-foreground font-medium">{to}</span> of{' '}
                <span className="text-foreground font-medium">{total}</span>
            </p>

            <ul className="flex flex-wrap items-center gap-1">
                {links.map((link, index) => {
                    const isPrevious = index === 0;
                    const isNext = index === lastIndex;
                    const isEllipsis =
                        !isPrevious && !isNext && link.url === null;
                    const content = isPrevious ? (
                        <>
                            <ChevronLeft aria-hidden="true" />
                            <span className="sr-only sm:not-sr-only">
                                Previous
                            </span>
                        </>
                    ) : isNext ? (
                        <>
                            <span className="sr-only sm:not-sr-only">Next</span>
                            <ChevronRight aria-hidden="true" />
                        </>
                    ) : (
                        link.label
                    );

                    if (isEllipsis) {
                        return (
                            <li
                                key={`${link.label}-${index}`}
                                className="text-muted-foreground px-1.5 text-sm"
                                aria-hidden="true"
                            >
                                …
                            </li>
                        );
                    }

                    const sizing =
                        isPrevious || isNext
                            ? 'px-2.5'
                            : 'min-w-8 px-2 tabular-nums';

                    return (
                        <li key={`${link.label}-${index}`}>
                            {link.url ? (
                                <Button
                                    variant={link.active ? 'default' : 'ghost'}
                                    size="sm"
                                    className={sizing}
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        preserveState
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        aria-label={
                                            isPrevious
                                                ? 'Previous page'
                                                : isNext
                                                  ? 'Next page'
                                                  : `Page ${link.label}`
                                        }
                                    >
                                        {content}
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className={sizing}
                                    disabled
                                >
                                    {content}
                                </Button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

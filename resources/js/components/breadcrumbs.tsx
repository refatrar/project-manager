import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb className="min-w-0">
                    <BreadcrumbList className="flex-nowrap">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            // On small screens only the last two crumbs fit.
                            const collapsible = index < breadcrumbs.length - 2;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem
                                        className={cn(
                                            'min-w-0',
                                            collapsible &&
                                                'hidden md:inline-flex',
                                        )}
                                    >
                                        {isLast ? (
                                            <BreadcrumbPage className="truncate font-medium">
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink
                                                asChild
                                                className="max-w-[14rem] truncate"
                                            >
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && (
                                        <BreadcrumbSeparator
                                            className={cn(
                                                'text-subtle-foreground',
                                                collapsible &&
                                                    'hidden md:block',
                                            )}
                                        />
                                    )}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}

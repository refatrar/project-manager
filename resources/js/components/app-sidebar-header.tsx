import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-12 shrink-0 items-center gap-2 px-3 md:px-5">
            <SidebarTrigger className="text-muted-foreground hover:bg-accent hover:text-foreground size-8" />
            {breadcrumbs.length > 0 ? (
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            ) : null}
        </header>
    );
}

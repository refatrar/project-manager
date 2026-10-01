import { Breadcrumbs } from '@/components/breadcrumbs';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="bg-background/90 supports-[backdrop-filter]:bg-background/75 sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b px-4 backdrop-blur md:rounded-t-xl md:px-6">
            <SidebarTrigger className="text-muted-foreground hover:text-foreground -ml-1.5 size-8" />
            {breadcrumbs.length > 0 ? (
                <>
                    <Separator orientation="vertical" className="h-4!" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </>
            ) : null}
        </header>
    );
}

import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export type NavGroup = {
    label?: string;
    items: NavItem[];
};

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { mostSpecificCurrentIndex } = useCurrentUrl();
    const visibleGroups = groups.filter((group) => group.items.length > 0);
    // One active item across every group, so two sections never light up together.
    const activeIndex = mostSpecificCurrentIndex(
        visibleGroups.flatMap((group) => group.items.map((item) => item.href)),
    );

    const starts = visibleGroups.map((_, groupIndex) =>
        visibleGroups
            .slice(0, groupIndex)
            .reduce((sum, group) => sum + group.items.length, 0),
    );

    return (
        <>
            {visibleGroups.map((group, groupIndex) => {
                const start = starts[groupIndex];

                return (
                    <SidebarGroup
                        key={group.label ?? `group-${groupIndex}`}
                        className="px-2 py-1"
                    >
                        {group.label ? (
                            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        ) : null}
                        <SidebarMenu className="gap-0.5">
                            {group.items.map((item, index) => {
                                const isActive = start + index === activeIndex;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isActive}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link
                                                href={item.href}
                                                prefetch
                                                aria-current={
                                                    isActive
                                                        ? 'page'
                                                        : undefined
                                                }
                                            >
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}

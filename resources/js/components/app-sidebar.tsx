import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    FolderKanban,
    LayoutGrid,
    ListChecks,
    ListTodo,
    ListTree,
    Plane,
    Search,
    Tag,
    Timer,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTeamAccess } from '@/hooks/use-team-access';
import { dashboard, myDay } from '@/routes';
import { index as meetingsIndex } from '@/routes/meetings';
import { index as projectsIndex } from '@/routes/projects';
import { index as labelsIndex } from '@/routes/setup/labels';
import { index as scopesIndex } from '@/routes/setup/scopes';
import { index as availabilityIndex } from '@/routes/availability';
import { index as taskTypesIndex } from '@/routes/setup/task-types';
import { index as teamCapacityIndex } from '@/routes/team-capacity';
import { index as timeLogsIndex } from '@/routes/time-logs';
import { index as timeOffIndex } from '@/routes/time-off-requests';
import { index as timesheetIndex } from '@/routes/timesheet';
import { index as timesheetApprovalsIndex } from '@/routes/timesheet-approvals';
import { index as todoListsIndex } from '@/routes/todo-lists';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const can = useTeamAccess();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';
    // The logo goes to the first page the role can open, not always the
    // dashboard (which a role without `dashboard.view` would 403 on).
    const homeUrl = page.props.teamHome ?? '/';

    const slug = page.props.currentTeam?.slug;
    // Each item keeps the exact gate it had before; empty groups are hidden.
    const only = (
        items: (NavItem | false | null | undefined | '')[],
    ): NavItem[] => items.filter((item): item is NavItem => Boolean(item));

    const groups: NavGroup[] = [
        {
            items: only([
                can('dashboard.view') && {
                    title: 'Dashboard',
                    href: dashboardUrl,
                    icon: LayoutGrid,
                },
                slug &&
                    can('my-day.view') && {
                        title: 'My Day',
                        href: myDay(slug),
                        icon: CalendarCheck,
                    },
            ]),
        },
        {
            label: 'Work',
            items: slug
                ? only([
                      can('projects.view') && {
                          title: 'Projects',
                          href: projectsIndex(slug),
                          icon: FolderKanban,
                      },
                      can('meetings.view') && {
                          title: 'Meetings',
                          href: meetingsIndex(slug),
                          icon: CalendarDays,
                      },
                      can('todos.manage') && {
                          title: 'My To-Dos',
                          href: todoListsIndex(slug),
                          icon: ListTodo,
                      },
                  ])
                : [],
        },
        {
            label: 'Time',
            items: slug
                ? only([
                      can('time-logs.manage') && {
                          title: 'Time Logs',
                          href: timeLogsIndex(slug),
                          icon: Timer,
                      },
                      can('timesheet.view') && {
                          title: 'Timesheet',
                          href: timesheetIndex(slug),
                          icon: ClipboardList,
                      },
                      can('time-off.view') && {
                          title: 'Time Off',
                          href: timeOffIndex(slug),
                          icon: Plane,
                      },
                  ])
                : [],
        },
        {
            label: 'Team',
            items: slug
                ? only([
                      page.props.canApproveTimesheets && {
                          title: 'Timesheet Approvals',
                          href: timesheetApprovalsIndex(slug),
                          icon: ClipboardCheck,
                      },
                      can('team-capacity.view') && {
                          title: 'Team Capacity',
                          href: teamCapacityIndex(slug),
                          icon: Users,
                      },
                      can('availability.view') && {
                          title: 'Find Available People',
                          href: availabilityIndex(slug),
                          icon: Search,
                      },
                  ])
                : [],
        },
        {
            label: 'Setup',
            items:
                slug && can('setup.manage')
                    ? [
                          {
                              title: 'Scopes',
                              href: scopesIndex(slug),
                              icon: ListTree,
                          },
                          {
                              title: 'Task types',
                              href: taskTypesIndex(slug),
                              icon: ListChecks,
                          },
                          {
                              title: 'Labels',
                              href: labelsIndex(slug),
                              icon: Tag,
                          },
                      ]
                    : [],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="gap-1 pb-1">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-1 py-1">
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter className="border-sidebar-border border-t">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarClock,
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
import { index as workScheduleIndex } from '@/routes/work-schedule';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(page.props.currentTeam
            ? [
                  {
                      title: 'My Day',
                      href: myDay(page.props.currentTeam.slug),
                      icon: CalendarCheck,
                  },
                  {
                      title: 'Projects',
                      href: projectsIndex(page.props.currentTeam.slug),
                      icon: FolderKanban,
                  },
                  {
                      title: 'Meetings',
                      href: meetingsIndex(page.props.currentTeam.slug),
                      icon: CalendarDays,
                  },
                  {
                      title: 'My To-Dos',
                      href: todoListsIndex(page.props.currentTeam.slug),
                      icon: ListTodo,
                  },
                  {
                      title: 'Work Schedule',
                      href: workScheduleIndex(page.props.currentTeam.slug),
                      icon: CalendarClock,
                  },
                  {
                      title: 'Time Off',
                      href: timeOffIndex(page.props.currentTeam.slug),
                      icon: Plane,
                  },
                  {
                      title: 'Time Logs',
                      href: timeLogsIndex(page.props.currentTeam.slug),
                      icon: Timer,
                  },
                  {
                      title: 'Timesheet',
                      href: timesheetIndex(page.props.currentTeam.slug),
                      icon: ClipboardList,
                  },
                  {
                      title: 'Timesheet Approvals',
                      href: timesheetApprovalsIndex(
                          page.props.currentTeam.slug,
                      ),
                      icon: ClipboardCheck,
                  },
                  ...(page.props.currentTeam.role === 'owner' ||
                  page.props.currentTeam.role === 'admin'
                      ? [
                            {
                                title: 'Find Available People',
                                href: availabilityIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: Search,
                            },
                            {
                                title: 'Team Capacity',
                                href: teamCapacityIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: Users,
                            },
                        ]
                      : []),
              ]
            : []),
    ];

    const setupNavItems: NavItem[] = page.props.currentTeam
        ? [
              {
                  title: 'Scopes',
                  href: scopesIndex(page.props.currentTeam.slug),
                  icon: ListTree,
              },
              {
                  title: 'Task types',
                  href: taskTypesIndex(page.props.currentTeam.slug),
                  icon: ListChecks,
              },
              {
                  title: 'Labels',
                  href: labelsIndex(page.props.currentTeam.slug),
                  icon: Tag,
              },
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
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

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {setupNavItems.length > 0 ? (
                    <NavMain items={setupNavItems} label="Setup" />
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

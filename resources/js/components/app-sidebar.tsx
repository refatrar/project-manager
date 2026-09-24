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

    const mainNavItems: NavItem[] = [
        ...(can('dashboard.view')
            ? [
                  {
                      title: 'Dashboard',
                      href: dashboardUrl,
                      icon: LayoutGrid,
                  },
              ]
            : []),
        ...(page.props.currentTeam
            ? (
                  [
                      can('my-day.view')
                          ? {
                                title: 'My Day',
                                href: myDay(page.props.currentTeam.slug),
                                icon: CalendarCheck,
                            }
                          : null,
                      can('projects.view')
                          ? {
                                title: 'Projects',
                                href: projectsIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: FolderKanban,
                            }
                          : null,
                      can('meetings.view')
                          ? {
                                title: 'Meetings',
                                href: meetingsIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: CalendarDays,
                            }
                          : null,
                      can('todos.manage')
                          ? {
                                title: 'My To-Dos',
                                href: todoListsIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: ListTodo,
                            }
                          : null,
                      can('time-off.view')
                          ? {
                                title: 'Time Off',
                                href: timeOffIndex(page.props.currentTeam.slug),
                                icon: Plane,
                            }
                          : null,
                      can('time-logs.manage')
                          ? {
                                title: 'Time Logs',
                                href: timeLogsIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: Timer,
                            }
                          : null,
                      can('timesheet.view')
                          ? {
                                title: 'Timesheet',
                                href: timesheetIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: ClipboardList,
                            }
                          : null,
                      page.props.canApproveTimesheets
                          ? {
                                title: 'Timesheet Approvals',
                                href: timesheetApprovalsIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: ClipboardCheck,
                            }
                          : null,
                      can('availability.view')
                          ? {
                                title: 'Find Available People',
                                href: availabilityIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: Search,
                            }
                          : null,
                      can('team-capacity.view')
                          ? {
                                title: 'Team Capacity',
                                href: teamCapacityIndex(
                                    page.props.currentTeam.slug,
                                ),
                                icon: Users,
                            }
                          : null,
                  ] as (NavItem | null)[]
              ).filter((item): item is NavItem => item !== null)
            : []),
    ];

    const setupNavItems: NavItem[] = page.props.currentTeam
        ? [
              ...(can('setup.manage')
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
                  : []),
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
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

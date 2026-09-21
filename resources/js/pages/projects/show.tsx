import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import ActivityFeed from '@/components/projects/activity-feed';
import AllocationList from '@/components/projects/allocation-list';
import BurndownChart from '@/components/projects/burndown-chart';
import KanbanBoard from '@/components/projects/kanban-board';
import MemberList from '@/components/projects/member-list';
import MilestoneList from '@/components/projects/milestone-list';
import ModuleTree from '@/components/projects/module-tree';
import ProjectArchiveModal from '@/components/projects/project-archive-modal';
import ProjectDeleteModal from '@/components/projects/project-delete-modal';
import ProjectFormModal from '@/components/projects/project-form-modal';
import SprintList from '@/components/projects/sprint-list';
import TaskListView from '@/components/projects/task-list-view';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index } from '@/routes/projects';
import type {
    Activity,
    AllocationStatusOption,
    BurndownPoint,
    CycleTimeReport,
    Milestone,
    MilestoneStatusOption,
    PriorityOption,
    ProjectDetail,
    ProjectHealthOption,
    ProjectMember,
    ProjectMemberRoleOption,
    ProjectModule,
    ProjectModuleStatusOption,
    ProjectProgress,
    ProjectStatusOption,
    ResourceAllocation,
    Sprint,
    SprintStatusOption,
    Task,
    TaskAssignmentRoleOption,
    TaskLabel,
    TaskStatusOption,
    TaskTypeOption,
    TeamMemberOption,
} from '@/types';

type Props = {
    project: ProjectDetail;
    modules: ProjectModule[];
    members: ProjectMember[];
    availableUsers: TeamMemberOption[];
    tasks: Task[];
    taskTypes: TaskTypeOption[];
    milestones: Milestone[];
    sprints: Sprint[];
    resourceAllocations: ResourceAllocation[];
    labels: TaskLabel[];
    progress: ProjectProgress;
    burndown: BurndownPoint[];
    activities: Activity[];
    cycleTime: CycleTimeReport;
    statusOptions: ProjectStatusOption[];
    priorityOptions: PriorityOption[];
    healthOptions: ProjectHealthOption[];
    moduleStatusOptions: ProjectModuleStatusOption[];
    memberRoleOptions: ProjectMemberRoleOption[];
    taskStatusOptions: TaskStatusOption[];
    taskAssignmentRoleOptions: TaskAssignmentRoleOption[];
    milestoneStatusOptions: MilestoneStatusOption[];
    sprintStatusOptions: SprintStatusOption[];
    allocationStatusOptions: AllocationStatusOption[];
};

const TABS = [
    'overview',
    'board',
    'list',
    'modules',
    'members',
    'milestones',
    'sprints',
    'allocations',
    'activity',
] as const;
type Tab = (typeof TABS)[number];

const tabLabels: Record<Tab, string> = {
    overview: 'Overview',
    board: 'Board',
    list: 'List',
    modules: 'Modules',
    members: 'Members',
    milestones: 'Milestones',
    sprints: 'Sprints',
    allocations: 'Allocations',
    activity: 'Activity',
};

export default function ProjectShow({
    project,
    modules,
    members,
    availableUsers,
    tasks,
    taskTypes,
    milestones,
    sprints,
    resourceAllocations,
    labels,
    progress,
    burndown,
    activities,
    cycleTime,
    statusOptions,
    priorityOptions,
    healthOptions,
    moduleStatusOptions,
    memberRoleOptions,
    taskStatusOptions,
    taskAssignmentRoleOptions,
    milestoneStatusOptions,
    sprintStatusOptions,
    allocationStatusOptions,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [tab, setTab] = useState<Tab>('overview');
    const [editOpen, setEditOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title={project.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`${project.code} · ${project.name}`}
                        description={project.description ?? undefined}
                    />

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditOpen(true)}
                            data-test="project-edit-button"
                        >
                            <Pencil className="h-4 w-4" /> Edit
                        </Button>
                        {!project.archived_at ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setArchiveOpen(true)}
                                data-test="project-archive-button"
                            >
                                Archive
                            </Button>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteOpen(true)}
                            data-test="project-delete-button"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <nav
                    className="flex gap-1 border-b"
                    role="tablist"
                    aria-label="Project sections"
                >
                    {TABS.map((value) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            data-test={`project-tab-${value}`}
                            onClick={() => setTab(value)}
                            className={cn(
                                'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                                tab === value
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
                            )}
                        >
                            {tabLabels[value]}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' ? (
                    <OverviewTab
                        project={project}
                        progress={progress}
                        burndown={burndown}
                        cycleTime={cycleTime}
                    />
                ) : null}
                {tab === 'board' ? (
                    <KanbanBoard
                        projectId={project.id}
                        tasks={tasks}
                        taskTypes={taskTypes}
                        modules={modules}
                        members={members}
                        milestones={milestones}
                        sprints={sprints}
                        labels={labels}
                        statusOptions={taskStatusOptions}
                        priorityOptions={priorityOptions}
                        assignmentRoleOptions={taskAssignmentRoleOptions}
                        onChanged={() =>
                            router.reload({
                                only: ['tasks', 'activities', 'cycleTime'],
                            })
                        }
                    />
                ) : null}
                {tab === 'list' ? (
                    <TaskListView
                        projectId={project.id}
                        tasks={tasks}
                        members={members}
                        milestones={milestones}
                        sprints={sprints}
                        labels={labels}
                    />
                ) : null}
                {tab === 'modules' ? (
                    <ModuleTree
                        projectId={project.id}
                        modules={modules}
                        statusOptions={moduleStatusOptions}
                        priorityOptions={priorityOptions}
                        onChanged={() =>
                            router.reload({ only: ['modules', 'activities'] })
                        }
                    />
                ) : null}
                {tab === 'members' ? (
                    <MemberList
                        projectId={project.id}
                        members={members}
                        availableUsers={availableUsers}
                        roleOptions={memberRoleOptions}
                        onChanged={() =>
                            router.reload({
                                only: ['members', 'availableUsers', 'activities'],
                            })
                        }
                    />
                ) : null}
                {tab === 'milestones' ? (
                    <MilestoneList
                        projectId={project.id}
                        milestones={milestones}
                        statusOptions={milestoneStatusOptions}
                        onChanged={() =>
                            router.reload({ only: ['milestones', 'activities'] })
                        }
                    />
                ) : null}
                {tab === 'sprints' ? (
                    <SprintList
                        projectId={project.id}
                        sprints={sprints}
                        statusOptions={sprintStatusOptions}
                        onChanged={() =>
                            router.reload({ only: ['sprints', 'activities'] })
                        }
                    />
                ) : null}
                {tab === 'allocations' ? (
                    <AllocationList
                        projectId={project.id}
                        allocations={resourceAllocations}
                        members={members}
                        statusOptions={allocationStatusOptions}
                        onChanged={() =>
                            router.reload({ only: ['resourceAllocations', 'activities'] })
                        }
                    />
                ) : null}
                {tab === 'activity' ? (
                    <ActivityFeed activities={activities} members={members} />
                ) : null}
            </div>

            <ProjectFormModal
                open={editOpen}
                onOpenChange={setEditOpen}
                project={project}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                healthOptions={healthOptions}
                onSaved={() => router.reload({ only: ['project'] })}
            />

            <ProjectArchiveModal
                project={project}
                open={archiveOpen}
                onOpenChange={setArchiveOpen}
                onArchived={() =>
                    router.reload({ only: ['project', 'activities'] })
                }
            />

            <ProjectDeleteModal
                project={project}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                onDeleted={() => {
                    if (teamSlug) {
                        router.visit(index(teamSlug));
                    }
                }}
            />
        </>
    );
}

function OverviewTab({
    project,
    progress,
    burndown,
    cycleTime,
}: {
    project: ProjectDetail;
    progress: ProjectProgress;
    burndown: BurndownPoint[];
    cycleTime: CycleTimeReport;
}) {
    const healthVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
        on_track: 'default',
        at_risk: 'secondary',
        off_track: 'destructive',
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Status</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Badge>{project.status.replace('_', ' ')}</Badge>
                        <p className="text-muted-foreground text-sm">
                            {progress.progressPercentage}% complete ·{' '}
                            {progress.completedTasks}/{progress.totalTasks} tasks
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Health</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Badge variant={healthVariant[project.health] ?? 'default'}>
                            {project.health.replace('_', ' ')}
                        </Badge>
                        <p className="text-muted-foreground text-sm">
                            {project.priority} priority
                        </p>
                    </CardContent>
                </Card>

                <Card data-test="overview-overdue">
                    <CardHeader>
                        <CardTitle>Overdue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            className={cn(
                                'text-2xl font-semibold',
                                progress.overdueTasks > 0 && 'text-destructive',
                            )}
                        >
                            {progress.overdueTasks}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            past due, still open
                        </p>
                    </CardContent>
                </Card>

                <Card data-test="overview-blocked">
                    <CardHeader>
                        <CardTitle>Blocked</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            className={cn(
                                'text-2xl font-semibold',
                                progress.blockedTasks > 0 && 'text-destructive',
                            )}
                        >
                            {progress.blockedTasks}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {progress.inProgressTasks} in progress
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Burndown</CardTitle>
                </CardHeader>
                <CardContent>
                    <BurndownChart points={burndown} />
                </CardContent>
            </Card>

            <CycleTimeCard cycleTime={cycleTime} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Owner</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {project.owner ? project.owner.name : 'Unassigned'}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Timeline</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <p>Start: {project.start_date ?? '—'}</p>
                        <p>End: {project.end_date ?? '—'}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Budget</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <p>Estimated hours: {project.estimated_hours ?? '—'}</p>
                        <p>
                            Budget:{' '}
                            {project.budget
                                ? `${project.budget} ${project.currency ?? ''}`
                                : '—'}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Client</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {project.client_name ?? 'Internal project'}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function formatDuration(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    if (minutes < 60) {
        return `${Math.round(minutes)}m`;
    }

    if (minutes < 1440) {
        return `${(minutes / 60).toFixed(1)}h`;
    }

    return `${(minutes / 1440).toFixed(1)}d`;
}

function CycleTimeCard({ cycleTime }: { cycleTime: CycleTimeReport }) {
    const maxAvgMinutes = Math.max(
        ...cycleTime.statusBreakdown.map((row) => row.avgMinutes),
        1,
    );

    return (
        <Card data-test="cycle-time-card">
            <CardHeader>
                <CardTitle>Cycle time</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Avg. lead time
                        </p>
                        <p
                            className="text-2xl font-semibold"
                            data-test="cycle-time-lead"
                        >
                            {formatDuration(cycleTime.avgLeadTimeMinutes)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Avg. cycle time
                        </p>
                        <p
                            className="text-2xl font-semibold"
                            data-test="cycle-time-cycle"
                        >
                            {formatDuration(cycleTime.avgCycleTimeMinutes)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Completed tasks
                        </p>
                        <p className="text-2xl font-semibold">
                            {cycleTime.completedTaskCount}
                        </p>
                    </div>
                </div>

                {cycleTime.statusBreakdown.length > 0 ? (
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            Average time spent per status
                        </p>
                        <ul className="space-y-1.5">
                            {cycleTime.statusBreakdown.map((row) => (
                                <li
                                    key={row.status}
                                    data-test="cycle-time-status-row"
                                    className="grid grid-cols-[8rem_1fr_3.5rem] items-center gap-3 text-sm"
                                >
                                    <span className="truncate">{row.label}</span>
                                    <span className="bg-muted h-2 overflow-hidden rounded-full">
                                        <span
                                            className="bg-chart-1 block h-full rounded-full"
                                            style={{
                                                width: `${(row.avgMinutes / maxAvgMinutes) * 100}%`,
                                            }}
                                        />
                                    </span>
                                    <span className="text-muted-foreground text-right">
                                        {formatDuration(row.avgMinutes)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No status transitions recorded yet.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

ProjectShow.layout = (props: {
    project: ProjectDetail;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.project.name,
            href: '#',
        },
    ],
});

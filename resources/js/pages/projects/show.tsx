import { Head, router, usePage } from '@inertiajs/react';
import {
    Archive,
    Ban,
    CalendarRange,
    CircleCheck,
    CircleDot,
    Pencil,
    TimerOff,
    Trash2,
} from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { ProgressBar } from '@/components/patterns/progress-bar';
import { StatCard } from '@/components/patterns/stat-card';
import {
    PriorityIndicator,
    StatusBadge,
} from '@/components/patterns/status-badge';
import ActivityFeed from '@/components/projects/activity-feed';
import AllocationList from '@/components/projects/allocation-list';
import BurndownChart from '@/components/projects/burndown-chart';
import EstimatedVsActual from '@/components/projects/estimated-vs-actual';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { statusMeta } from '@/lib/status';
import { cn } from '@/lib/utils';
import { index } from '@/routes/projects';
import type {
    Activity,
    AllocationStatusOption,
    BurndownPoint,
    CycleTimeReport,
    EstimatedVsActualRow,
    Milestone,
    MilestoneStatusOption,
    PriorityOption,
    Project,
    ProjectDetail,
    ProjectHealthOption,
    ProjectMember,
    ProjectMemberCapacity,
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

type ProjectPayload = Project & Partial<Omit<ProjectDetail, keyof Project>>;

function isProjectDetail(project: ProjectPayload): project is ProjectDetail {
    return 'description' in project;
}

type Props = {
    project: ProjectPayload;
    canManageProject: boolean;
    modules: ProjectModule[];
    members: ProjectMember[];
    memberCapacity: ProjectMemberCapacity[];
    availableUsers: TeamMemberOption[];
    teamMembers: TeamMemberOption[];
    tasks: Task[];
    taskTypes: TaskTypeOption[];
    milestones: Milestone[];
    sprints: Sprint[];
    resourceAllocations: ResourceAllocation[];
    estimatedVsActual: EstimatedVsActualRow[];
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
    memberCapacity,
    availableUsers,
    teamMembers,
    tasks,
    taskTypes,
    milestones,
    sprints,
    resourceAllocations,
    estimatedVsActual,
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
    canManageProject,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [tab, setTab] = useState<Tab>('overview');
    const [editOpen, setEditOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [descriptionExpanded, setDescriptionExpanded] = useState(false);
    const tabRefs = useRef<Partial<Record<Tab, HTMLButtonElement | null>>>({});

    const tabCounts: Partial<Record<Tab, number>> = {
        board: tasks?.length,
        list: tasks?.length,
        modules: modules?.length,
        members: members?.length,
        milestones: milestones?.length,
        sprints: sprints?.length,
        allocations: resourceAllocations?.length,
    };

    // WAI-ARIA tabs: arrow keys move between tabs, Home/End jump to the ends.
    const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        const current = TABS.indexOf(tab);
        const next =
            event.key === 'ArrowRight'
                ? (current + 1) % TABS.length
                : event.key === 'ArrowLeft'
                  ? (current - 1 + TABS.length) % TABS.length
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? TABS.length - 1
                      : null;

        if (next === null) {
            return;
        }

        event.preventDefault();
        setTab(TABS[next]);
        tabRefs.current[TABS[next]]?.focus();
    };

    const description = isProjectDetail(project) ? project.description : null;

    return (
        <>
            <Head title={project.name} />

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-5 p-4 md:p-6 2xl:p-8">
                <PageHeader
                    eyebrow={
                        <span className="font-mono text-xs tracking-wide">
                            {project.code}
                        </span>
                    }
                    title={project.name}
                    meta={
                        <>
                            <StatusBadge
                                kind="project"
                                value={project.status}
                                label={optionLabel(
                                    statusOptions,
                                    project.status,
                                )}
                            />
                            <StatusBadge
                                kind="health"
                                value={project.health}
                                label={optionLabel(
                                    healthOptions,
                                    project.health,
                                )}
                            />
                            <PriorityIndicator
                                value={project.priority}
                                label={`${optionLabel(priorityOptions, project.priority)} priority`}
                                className="ml-1"
                            />
                            {project.archived_at ? (
                                <Badge variant="neutral">
                                    <Archive aria-hidden="true" />
                                    Archived
                                </Badge>
                            ) : null}
                        </>
                    }
                    actions={
                        canManageProject ? (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditOpen(true)}
                                    data-test="project-edit-button"
                                >
                                    <Pencil /> Edit
                                </Button>
                                {!project.archived_at ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setArchiveOpen(true)}
                                        data-test="project-archive-button"
                                    >
                                        <Archive /> Archive
                                    </Button>
                                ) : null}
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            className="hover:text-destructive"
                                            onClick={() => setDeleteOpen(true)}
                                            data-test="project-delete-button"
                                            aria-label="Delete project"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Delete project
                                    </TooltipContent>
                                </Tooltip>
                            </>
                        ) : null
                    }
                />

                {description ? (
                    <div className="-mt-1 max-w-4xl">
                        <p
                            id="project-description"
                            className={cn(
                                'text-muted-foreground text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-line',
                                !descriptionExpanded && 'line-clamp-2',
                            )}
                        >
                            {description}
                        </p>
                        {description.length > 180 ? (
                            <button
                                type="button"
                                className="text-primary focus-visible:ring-ring mt-1 rounded-sm text-[0.8125rem] font-medium hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                aria-expanded={descriptionExpanded}
                                aria-controls="project-description"
                                onClick={() =>
                                    setDescriptionExpanded(
                                        (expanded) => !expanded,
                                    )
                                }
                            >
                                {descriptionExpanded
                                    ? 'Show less'
                                    : 'Show more'}
                            </button>
                        ) : null}
                    </div>
                ) : null}

                <div className="relative -mx-4 border-b md:mx-0">
                    <div
                        className="flex [scrollbar-width:none] gap-1 overflow-x-auto px-4 md:px-0 [&::-webkit-scrollbar]:hidden"
                        role="tablist"
                        aria-label="Project sections"
                    >
                        {TABS.map((value) => {
                            const selected = tab === value;
                            const count = tabCounts[value];

                            return (
                                <button
                                    key={value}
                                    ref={(element) => {
                                        tabRefs.current[value] = element;
                                    }}
                                    type="button"
                                    role="tab"
                                    id={`project-tab-${value}`}
                                    aria-selected={selected}
                                    aria-controls="project-tabpanel"
                                    tabIndex={selected ? 0 : -1}
                                    data-test={`project-tab-${value}`}
                                    onClick={() => setTab(value)}
                                    onKeyDown={onTabKeyDown}
                                    className={cn(
                                        'focus-visible:ring-ring relative -mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-t-md focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset',
                                        selected
                                            ? 'border-primary text-foreground'
                                            : 'text-muted-foreground hover:text-foreground hover:border-border border-transparent',
                                    )}
                                >
                                    {tabLabels[value]}
                                    {count !== undefined ? (
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 text-[0.6875rem] leading-4 font-semibold tabular-nums',
                                                selected
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {count}
                                        </span>
                                    ) : null}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div
                    id="project-tabpanel"
                    role="tabpanel"
                    aria-labelledby={`project-tab-${tab}`}
                    className="min-w-0 focus-visible:outline-none"
                >
                    {tab === 'overview' ? (
                        <OverviewTab
                            project={project}
                            progress={progress}
                            burndown={burndown}
                            cycleTime={cycleTime}
                            statusOptions={statusOptions}
                            healthOptions={healthOptions}
                            priorityOptions={priorityOptions}
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
                            canManage={canManageProject}
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
                            statusOptions={taskStatusOptions}
                            priorityOptions={priorityOptions}
                        />
                    ) : null}
                    {tab === 'modules' ? (
                        <ModuleTree
                            projectId={project.id}
                            modules={modules}
                            statusOptions={moduleStatusOptions}
                            priorityOptions={priorityOptions}
                            canManage={canManageProject}
                            onChanged={() =>
                                router.reload({
                                    only: ['modules', 'activities'],
                                })
                            }
                        />
                    ) : null}
                    {tab === 'members' ? (
                        <MemberList
                            projectId={project.id}
                            members={members}
                            capacity={memberCapacity}
                            availableUsers={availableUsers}
                            roleOptions={memberRoleOptions}
                            canManage={canManageProject}
                            onChanged={() =>
                                router.reload({
                                    only: [
                                        'members',
                                        'memberCapacity',
                                        'availableUsers',
                                        'activities',
                                    ],
                                })
                            }
                        />
                    ) : null}
                    {tab === 'milestones' ? (
                        <MilestoneList
                            projectId={project.id}
                            milestones={milestones}
                            statusOptions={milestoneStatusOptions}
                            canManage={canManageProject}
                            onChanged={() =>
                                router.reload({
                                    only: ['milestones', 'activities'],
                                })
                            }
                        />
                    ) : null}
                    {tab === 'sprints' ? (
                        <SprintList
                            projectId={project.id}
                            sprints={sprints}
                            statusOptions={sprintStatusOptions}
                            canManage={canManageProject}
                            onChanged={() =>
                                router.reload({
                                    only: ['sprints', 'activities'],
                                })
                            }
                        />
                    ) : null}
                    {tab === 'allocations' ? (
                        <div className="space-y-6">
                            <EstimatedVsActual rows={estimatedVsActual} />
                            <AllocationList
                                projectId={project.id}
                                allocations={resourceAllocations}
                                members={members}
                                statusOptions={allocationStatusOptions}
                                canManage={canManageProject}
                                onChanged={() =>
                                    router.reload({
                                        only: [
                                            'resourceAllocations',
                                            'estimatedVsActual',
                                            'activities',
                                        ],
                                    })
                                }
                            />
                        </div>
                    ) : null}
                    {tab === 'activity' ? (
                        <ActivityFeed
                            activities={activities}
                            members={members}
                        />
                    ) : null}
                </div>
            </div>

            {canManageProject && isProjectDetail(project) ? (
                <>
                    <ProjectFormModal
                        open={editOpen}
                        onOpenChange={setEditOpen}
                        project={project}
                        statusOptions={statusOptions}
                        priorityOptions={priorityOptions}
                        healthOptions={healthOptions}
                        teamMembers={teamMembers}
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
            ) : null}
        </>
    );
}

function OverviewTab({
    project,
    progress,
    burndown,
    cycleTime,
    statusOptions,
    healthOptions,
    priorityOptions,
}: {
    project: ProjectPayload;
    progress: ProjectProgress;
    burndown: BurndownPoint[];
    cycleTime: CycleTimeReport;
    statusOptions: ProjectStatusOption[];
    healthOptions: ProjectHealthOption[];
    priorityOptions: PriorityOption[];
}) {
    const healthTone = statusMeta('health', project.health).tone;

    return (
        <div className="space-y-5">
            <div className="grid grid-cols-3 gap-3 lg:grid-cols-4">
                <div className="col-span-3 flex flex-col gap-1 lg:col-span-1">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-muted-foreground text-[0.8125rem] font-medium">
                            Progress
                        </p>
                        <CircleCheck
                            className="text-muted-foreground size-4"
                            aria-hidden="true"
                        />
                    </div>
                    <p className="text-2xl leading-8 font-semibold tracking-tight tabular-nums">
                        {progress.progressPercentage}%
                    </p>
                    <ProgressBar
                        value={progress.progressPercentage}
                        label="Project progress"
                        tone={healthTone === 'neutral' ? 'primary' : healthTone}
                        className="my-1"
                    />
                    <p className="text-subtle-foreground text-xs tabular-nums">
                        {progress.completedTasks} of {progress.totalTasks} tasks
                        complete
                    </p>
                </div>

                <StatCard
                    label="In progress"
                    value={progress.inProgressTasks}
                    hint="Tasks being worked on"
                    icon={CircleDot}
                />
                <StatCard
                    label="Overdue"
                    value={progress.overdueTasks}
                    hint="Past due, still open"
                    icon={TimerOff}
                    tone={progress.overdueTasks > 0 ? 'destructive' : 'neutral'}
                    data-test="overview-overdue"
                />
                <StatCard
                    label="Blocked"
                    value={progress.blockedTasks}
                    hint="Waiting on something"
                    icon={Ban}
                    tone={progress.blockedTasks > 0 ? 'destructive' : 'neutral'}
                    data-test="overview-blocked"
                />
            </div>

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0 space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Burndown</CardTitle>
                            <p className="text-muted-foreground text-[0.8125rem]">
                                Open tasks over the last 30 days
                            </p>
                        </CardHeader>
                        <CardContent>
                            <BurndownChart points={burndown} />
                        </CardContent>
                    </Card>

                    <CycleTimeCard cycleTime={cycleTime} />
                </div>

                <Card className="self-start">
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y text-sm">
                            <DetailRow label="Status">
                                <StatusBadge
                                    kind="project"
                                    value={project.status}
                                    label={optionLabel(
                                        statusOptions,
                                        project.status,
                                    )}
                                />
                            </DetailRow>
                            <DetailRow label="Health">
                                <StatusBadge
                                    kind="health"
                                    value={project.health}
                                    label={optionLabel(
                                        healthOptions,
                                        project.health,
                                    )}
                                />
                            </DetailRow>
                            <DetailRow label="Priority">
                                <PriorityIndicator
                                    value={project.priority}
                                    label={optionLabel(
                                        priorityOptions,
                                        project.priority,
                                    )}
                                    showLabel
                                    className="text-foreground"
                                />
                            </DetailRow>
                            {project.owner !== undefined ? (
                                <DetailRow label="Owner">
                                    {project.owner ? (
                                        project.owner.name
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Unassigned
                                        </span>
                                    )}
                                </DetailRow>
                            ) : null}
                            {project.project_lead !== undefined ? (
                                <DetailRow label="Project lead">
                                    {project.project_lead ? (
                                        project.project_lead.name
                                    ) : (
                                        <span className="text-muted-foreground">
                                            None
                                        </span>
                                    )}
                                </DetailRow>
                            ) : null}
                            {project.client_name !== undefined ? (
                                <DetailRow label="Client">
                                    {project.client_name ?? (
                                        <span className="text-muted-foreground">
                                            Internal project
                                        </span>
                                    )}
                                </DetailRow>
                            ) : null}
                            <DetailRow label="Timeline">
                                <span className="inline-flex items-center gap-1.5 tabular-nums">
                                    <CalendarRange
                                        className="text-muted-foreground size-3.5"
                                        aria-hidden="true"
                                    />
                                    {project.start_date || project.end_date
                                        ? `${formatDate(project.start_date)} – ${formatDate(project.end_date)}`
                                        : 'Not set'}
                                </span>
                            </DetailRow>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-11 items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="min-w-0 truncate text-right font-medium">
                {children}
            </dd>
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
                            className="text-2xl font-semibold tracking-tight tabular-nums"
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
                            className="text-2xl font-semibold tracking-tight tabular-nums"
                            data-test="cycle-time-cycle"
                        >
                            {formatDuration(cycleTime.avgCycleTimeMinutes)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Completed tasks
                        </p>
                        <p className="text-2xl font-semibold tracking-tight tabular-nums">
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
                                    className="grid grid-cols-[minmax(0,8rem)_1fr_3.5rem] items-center gap-3 text-sm"
                                >
                                    <span className="truncate">
                                        {row.label}
                                    </span>
                                    <span className="bg-muted h-2 overflow-hidden rounded-full">
                                        <span
                                            className="bg-primary block h-full rounded-full"
                                            style={{
                                                width: `${(row.avgMinutes / maxAvgMinutes) * 100}%`,
                                            }}
                                        />
                                    </span>
                                    <span className="text-muted-foreground text-right tabular-nums">
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
    project: ProjectPayload;
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

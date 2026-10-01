import { Head, Link, router, usePage } from '@inertiajs/react';
import { CornerLeftUp, ListTree, Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { EmptyState } from '@/components/patterns/empty-state';
import { LabelChip } from '@/components/patterns/label-chip';
import { PageHeader } from '@/components/patterns/page-header';
import { ProgressBar } from '@/components/patterns/progress-bar';
import {
    PriorityIndicator,
    StatusBadge,
} from '@/components/patterns/status-badge';
import { UserAvatar } from '@/components/patterns/user-avatar';
import TaskChecklist from '@/components/projects/task-checklist';
import TaskDeleteModal from '@/components/projects/task-delete-modal';
import TaskDependencyEditor from '@/components/projects/task-dependency-editor';
import TaskFormModal from '@/components/projects/task-form-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { show as showTask } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    PriorityOption,
    ProjectModule,
    SprintOption,
    Task,
    TaskDependencyTypeOption,
    TaskDetail,
    TaskLabel,
    TaskReference,
    TaskStatusOption,
    TaskTypeOption,
    TeamMemberOption,
    TodoList,
} from '@/types';

type Props = {
    project: { id: number; code: string; name: string };
    task: TaskDetail;
    taskTypes: TaskTypeOption[];
    modules: ProjectModule[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
    taskCandidates: TaskReference[];
    statusOptions: TaskStatusOption[];
    priorityOptions: PriorityOption[];
    dependencyTypeOptions: TaskDependencyTypeOption[];
    checklist: TodoList;
    projectMembers: TeamMemberOption[];
    canManageTask: boolean;
    canDeleteTask: boolean;
};

export default function TaskShow({
    project,
    task,
    taskTypes,
    modules,
    milestones,
    sprints,
    labels,
    taskCandidates,
    statusOptions,
    priorityOptions,
    dependencyTypeOptions,
    checklist,
    projectMembers,
    canManageTask,
    canDeleteTask,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [addingSubtask, setAddingSubtask] = useState(false);

    const reload = (only: string[]) => router.reload({ only });

    return (
        <>
            <Head title={`${task.reference} — ${task.title}`} />

            <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <PageHeader
                    eyebrow={
                        task.parent ? (
                            <Link
                                href={
                                    teamSlug
                                        ? showTask.url([
                                              teamSlug,
                                              project.id,
                                              task.parent.id,
                                          ])
                                        : '#'
                                }
                                className="hover:text-foreground focus-visible:ring-ring inline-flex max-w-full items-center gap-1 rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <CornerLeftUp
                                    className="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span className="truncate">
                                    <span className="sr-only">
                                        Parent task:{' '}
                                    </span>
                                    <span className="font-mono">
                                        {task.parent.reference}
                                    </span>{' '}
                                    {task.parent.title}
                                </span>
                            </Link>
                        ) : (
                            <span className="font-mono text-xs tracking-wide">
                                {task.reference}
                            </span>
                        )
                    }
                    title={task.title}
                    meta={
                        <>
                            {task.parent ? (
                                <span className="text-muted-foreground mr-1 font-mono text-xs">
                                    {task.reference}
                                </span>
                            ) : null}
                            <StatusBadge
                                kind="task"
                                value={task.status}
                                label={optionLabel(statusOptions, task.status)}
                            />
                            <PriorityIndicator
                                value={task.priority}
                                label={`${optionLabel(priorityOptions, task.priority)} priority`}
                                showLabel
                                className="ml-1"
                            />
                        </>
                    }
                    actions={
                        canManageTask || canDeleteTask ? (
                            <>
                                {canManageTask ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditOpen(true)}
                                        data-test="task-edit-button"
                                    >
                                        <Pencil /> Edit
                                    </Button>
                                ) : null}
                                {canDeleteTask ? (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                className="hover:text-destructive"
                                                onClick={() =>
                                                    setDeleteOpen(true)
                                                }
                                                data-test="task-delete-button"
                                                aria-label="Delete task"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Delete task
                                        </TooltipContent>
                                    </Tooltip>
                                ) : null}
                            </>
                        ) : null
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <aside
                        aria-label="Task details"
                        className="bg-card rounded-lg border lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1"
                    >
                        <dl className="divide-y px-4 text-sm">
                            <PropertyRow label="Status">
                                <StatusBadge
                                    kind="task"
                                    value={task.status}
                                    label={optionLabel(
                                        statusOptions,
                                        task.status,
                                    )}
                                />
                            </PropertyRow>
                            <PropertyRow label="Priority">
                                <PriorityIndicator
                                    value={task.priority}
                                    label={optionLabel(
                                        priorityOptions,
                                        task.priority,
                                    )}
                                    showLabel
                                    className="text-foreground"
                                />
                            </PropertyRow>
                            <PropertyRow label="Type">
                                {task.taskType.name}
                            </PropertyRow>
                            <PropertyRow label="Assignees" stacked>
                                {task.assignees.length > 0 ? (
                                    <ul className="space-y-1.5">
                                        {task.assignees.map((assignee) => (
                                            <li
                                                key={assignee.id}
                                                className="flex items-center gap-2"
                                            >
                                                <UserAvatar
                                                    name={assignee.name}
                                                    size="sm"
                                                />
                                                <span className="truncate">
                                                    {assignee.name}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <span className="text-muted-foreground font-normal">
                                        Unassigned
                                    </span>
                                )}
                            </PropertyRow>
                            <PropertyRow label="Labels" stacked>
                                {task.labels.length > 0 ? (
                                    <div className="flex flex-wrap gap-1">
                                        {task.labels.map((label) => (
                                            <LabelChip
                                                key={label.id}
                                                name={label.name}
                                                color={label.color}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground font-normal">
                                        No labels
                                    </span>
                                )}
                            </PropertyRow>
                            <PropertyRow label="Due">
                                <span
                                    className={cn(
                                        'tabular-nums',
                                        task.is_overdue &&
                                            'text-destructive-foreground',
                                    )}
                                >
                                    {formatDate(task.due_at, 'No due date')}
                                    {task.is_overdue ? (
                                        <span className="ml-1.5 text-xs">
                                            (overdue)
                                        </span>
                                    ) : null}
                                </span>
                            </PropertyRow>
                            <PropertyRow label="Progress" stacked>
                                <div className="flex items-center gap-3">
                                    <ProgressBar
                                        value={task.progress_percentage}
                                        label="Task progress"
                                    />
                                    <span className="w-10 shrink-0 text-right tabular-nums">
                                        {task.progress_percentage}%
                                    </span>
                                </div>
                            </PropertyRow>
                            <PropertyRow label="Hours" stacked>
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <HoursCell
                                        label="Estimated"
                                        value={task.estimated_hours}
                                    />
                                    <HoursCell
                                        label="Logged"
                                        value={task.logged_hours}
                                    />
                                    <HoursCell
                                        label="Remaining"
                                        value={task.remaining_hours}
                                    />
                                </div>
                            </PropertyRow>
                        </dl>
                    </aside>

                    <div className="min-w-0 space-y-6 lg:col-start-1 lg:row-start-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Description</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {task.description ? (
                                    <p className="text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-line">
                                        {task.description}
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        No description.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Checklist</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TaskChecklist
                                    checklist={checklist}
                                    projectMembers={projectMembers}
                                    taskTypes={taskTypes}
                                    onChanged={() =>
                                        reload(['checklist', 'task'])
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-3">
                                    <CardTitle className="flex items-center gap-2">
                                        Subtasks
                                        {task.subtasks.length > 0 ? (
                                            <span className="bg-muted text-muted-foreground rounded-full px-1.5 text-[0.6875rem] leading-4 font-semibold tabular-nums">
                                                {task.subtasks.length}
                                            </span>
                                        ) : null}
                                    </CardTitle>
                                    {/* Not gated by canManageTask: TaskPolicy::create is broader
                                        than update — any member who can view this task may add one. */}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setAddingSubtask(true)}
                                        data-test="subtask-add"
                                    >
                                        <Plus /> Add subtask
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {task.subtasks.length > 0 ? (
                                    <ul className="divide-y overflow-hidden rounded-md border">
                                        {task.subtasks.map((subtask: Task) => (
                                            <li key={subtask.id}>
                                                <Link
                                                    href={
                                                        teamSlug
                                                            ? showTask.url([
                                                                  teamSlug,
                                                                  project.id,
                                                                  subtask.id,
                                                              ])
                                                            : '#'
                                                    }
                                                    data-test="subtask-row"
                                                    className="hover:bg-accent/50 focus-visible:ring-ring flex items-center justify-between gap-3 px-3 py-2.5 transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                                >
                                                    <span className="min-w-0 text-sm [overflow-wrap:anywhere]">
                                                        <span className="text-subtle-foreground font-mono text-xs">
                                                            {subtask.reference}
                                                        </span>{' '}
                                                        <span className="font-medium">
                                                            {subtask.title}
                                                        </span>
                                                    </span>
                                                    <StatusBadge
                                                        kind="task"
                                                        value={subtask.status}
                                                        label={optionLabel(
                                                            statusOptions,
                                                            subtask.status,
                                                        )}
                                                        className="shrink-0"
                                                    />
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <EmptyState
                                        compact
                                        icon={ListTree}
                                        title="No subtasks yet"
                                        description="Break this task into smaller pieces of work."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Dependencies</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TaskDependencyEditor
                                    projectId={project.id}
                                    taskId={task.id}
                                    dependencies={task.dependencies}
                                    candidates={taskCandidates}
                                    typeOptions={dependencyTypeOptions}
                                    canManage={canManageTask}
                                    onChanged={() =>
                                        reload(['task', 'taskCandidates'])
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <TaskFormModal
                open={editOpen}
                onOpenChange={setEditOpen}
                projectId={project.id}
                task={task}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={() => reload(['task', 'checklist'])}
            />

            <TaskFormModal
                open={addingSubtask}
                onOpenChange={setAddingSubtask}
                projectId={project.id}
                defaultParentId={task.id}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={() => reload(['task'])}
            />

            <TaskDeleteModal
                projectId={project.id}
                task={task}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                onDeleted={() => {
                    if (teamSlug) {
                        router.visit(showProject.url([teamSlug, project.id]));
                    }
                }}
            />
        </>
    );
}

TaskShow.layout = (props: {
    project: { id: number; code: string; name: string };
    task: TaskDetail;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: props.currentTeam
                ? projectsIndex(props.currentTeam.slug)
                : '/',
        },
        {
            title: props.project.name,
            href: props.currentTeam
                ? showProject.url([props.currentTeam.slug, props.project.id])
                : '#',
        },
        {
            title: props.task.reference,
            href: '#',
        },
    ],
});

function PropertyRow({
    label,
    stacked = false,
    children,
}: {
    label: string;
    stacked?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'py-3',
                stacked
                    ? 'space-y-2'
                    : 'flex min-h-11 items-center justify-between gap-4',
            )}
        >
            <dt className="text-muted-foreground shrink-0 text-[0.8125rem]">
                {label}
            </dt>
            <dd className={cn('min-w-0 font-medium', !stacked && 'text-right')}>
                {children}
            </dd>
        </div>
    );
}

function HoursCell({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="bg-muted/60 rounded-md px-2 py-1.5">
            <p className="text-sm font-semibold tabular-nums">{value ?? '—'}</p>
            <p className="text-subtle-foreground text-[0.6875rem]">{label}</p>
        </div>
    );
}

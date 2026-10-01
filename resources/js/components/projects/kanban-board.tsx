import { Link, useHttp, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    ChevronUp,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { LabelChip } from '@/components/patterns/label-chip';
import { PriorityIndicator } from '@/components/patterns/status-badge';
import { AvatarStack } from '@/components/patterns/user-avatar';
import TaskAssignmentsModal from '@/components/projects/task-assignments-modal';
import TaskDeleteModal from '@/components/projects/task-delete-modal';
import TaskFormModal from '@/components/projects/task-form-modal';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { move, show as showTask } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    PriorityOption,
    ProjectMember,
    ProjectModule,
    SprintOption,
    Task,
    TaskAssignmentRoleOption,
    TaskLabel,
    TaskStatus,
    TaskStatusOption,
    TaskTypeOption,
} from '@/types';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { statusMeta, toneText } from '@/lib/status';
import { cn } from '@/lib/utils';

const MAX_CARD_LABELS = 2;

type MovedResponse = {
    task: Task;
    message: string;
};

type Props = {
    projectId: number;
    tasks: Task[];
    taskTypes: TaskTypeOption[];
    modules: ProjectModule[];
    members: ProjectMember[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
    statusOptions: TaskStatusOption[];
    priorityOptions: PriorityOption[];
    assignmentRoleOptions: TaskAssignmentRoleOption[];
    canManage: boolean;
    onChanged: () => void;
};

export default function KanbanBoard({
    projectId,
    tasks,
    taskTypes,
    modules,
    members,
    milestones,
    sprints,
    labels,
    statusOptions,
    priorityOptions,
    assignmentRoleOptions,
    canManage,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const moveForm = useHttp<
        { status: TaskStatus; position: number },
        MovedResponse
    >({
        status: 'backlog',
        position: 0,
    });

    const [addingToStatus, setAddingToStatus] = useState<TaskStatus | null>(
        null,
    );
    const [editingTask, setEditingTask] = useState<Task | null>(null);
    const [deletingTask, setDeletingTask] = useState<Task | null>(null);
    const [assigningTaskId, setAssigningTaskId] = useState<number | null>(null);
    const assigningTask =
        tasks.find((task) => task.id === assigningTaskId) ?? null;

    const byStatus = useMemo(() => {
        const groups = new Map<TaskStatus, Task[]>();

        for (const task of tasks) {
            const column = groups.get(task.status) ?? [];
            column.push(task);
            groups.set(task.status, column);
        }

        for (const column of groups.values()) {
            column.sort((a, b) => a.position - b.position || a.id - b.id);
        }

        return groups;
    }, [tasks]);

    const moveTask = (task: Task, status: TaskStatus, position: number) => {
        if (!teamSlug) {
            return;
        }

        moveForm.transform(() => ({ status, position }));

        void moveForm.patch(move.url([teamSlug, projectId, task.id]), {
            onSuccess: () => onChanged(),
            onError: () => toast.error('Could not move that task.'),
        });
    };

    // `position` is sent as the slot index within the target column; the
    // server renumbers the column around it (ChangeTaskStatus).
    const moveWithinColumn = (task: Task, direction: -1 | 1) => {
        const column = byStatus.get(task.status) ?? [];
        const index = column.findIndex((row) => row.id === task.id);
        const target = index + direction;

        if (index === -1 || target < 0 || target >= column.length) {
            return;
        }

        moveTask(task, task.status, target);
    };

    const changeColumn = (task: Task, status: TaskStatus) => {
        moveTask(task, status, (byStatus.get(status) ?? []).length);
    };

    return (
        <div className="min-w-0 space-y-4">
            {/* The whole board scrolls sideways; each column scrolls its own cards. */}
            <div
                className="-mx-4 flex h-[calc(100svh-16rem)] min-h-96 snap-x gap-3 overflow-x-auto overflow-y-hidden px-4 pb-3 md:mx-0 md:px-0"
                data-test="board-scroll"
            >
                {statusOptions.map((statusOption) => {
                    const column = byStatus.get(statusOption.value) ?? [];
                    const meta = statusMeta('task', statusOption.value);
                    const StatusIcon = meta.icon;

                    return (
                        <section
                            key={statusOption.value}
                            aria-label={`${statusOption.label}, ${column.length} tasks`}
                            className="bg-secondary/70 flex h-full w-[min(18.5rem,calc(100vw-3rem))] shrink-0 snap-start flex-col rounded-lg"
                            data-test={`board-column-${statusOption.value}`}
                        >
                            <div className="flex shrink-0 items-center justify-between gap-2 py-2 pr-1.5 pl-3">
                                <h3 className="flex min-w-0 items-center gap-2 text-[0.8125rem] font-semibold">
                                    <StatusIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            toneText[meta.tone],
                                        )}
                                        aria-hidden="true"
                                    />
                                    <span className="truncate">
                                        {statusOption.label}
                                    </span>
                                    <span className="bg-background text-muted-foreground rounded-full border px-1.5 text-[0.6875rem] leading-4 font-semibold tabular-nums">
                                        {column.length}
                                    </span>
                                </h3>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    className="shrink-0"
                                    onClick={() =>
                                        setAddingToStatus(statusOption.value)
                                    }
                                    data-test="board-add-task"
                                    aria-label={`Add task to ${statusOption.label}`}
                                >
                                    <Plus />
                                </Button>
                            </div>

                            <div
                                className="min-h-0 flex-1 space-y-2 overflow-y-auto px-2 pb-2"
                                data-test="board-column-scroll"
                            >
                                {column.length === 0 ? (
                                    <p className="text-subtle-foreground px-3 py-8 text-center text-xs">
                                        Nothing in {statusOption.label.toLowerCase()}
                                    </p>
                                ) : null}
                                {column.map((task, index) => (
                                    <article
                                        key={task.id}
                                        data-test="task-card"
                                        className="group/card bg-card hover:bg-accent/40 min-w-0 rounded-lg border transition-colors duration-200"
                                    >
                                        <div className="space-y-2.5 p-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <Link
                                                    href={
                                                        teamSlug
                                                            ? showTask.url([
                                                                  teamSlug,
                                                                  projectId,
                                                                  task.id,
                                                              ])
                                                            : '#'
                                                    }
                                                    className="focus-visible:ring-ring min-w-0 flex-1 rounded-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    data-test="task-card-link"
                                                >
                                                    <p className="text-subtle-foreground truncate font-mono text-[0.6875rem] leading-4">
                                                        {task.reference}
                                                    </p>
                                                    <p className="mt-0.5 text-sm leading-5 font-medium [overflow-wrap:anywhere] hover:underline">
                                                        {task.title}
                                                    </p>
                                                </Link>
                                                {task.can_change_status ? (
                                                    <div className="-mt-1 -mr-1 flex shrink-0 flex-col">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-xs"
                                                            className="size-6"
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onClick={() =>
                                                                moveWithinColumn(
                                                                    task,
                                                                    -1,
                                                                )
                                                            }
                                                            data-test="task-move-up"
                                                            aria-label="Move task up"
                                                        >
                                                            <ChevronUp />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-xs"
                                                            className="size-6"
                                                            disabled={
                                                                index ===
                                                                column.length -
                                                                    1
                                                            }
                                                            onClick={() =>
                                                                moveWithinColumn(
                                                                    task,
                                                                    1,
                                                                )
                                                            }
                                                            data-test="task-move-down"
                                                            aria-label="Move task down"
                                                        >
                                                            <ChevronDown />
                                                        </Button>
                                                    </div>
                                                ) : null}
                                            </div>

                                            <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                <span className="bg-muted text-muted-foreground max-w-full truncate rounded-md px-1.5 py-0.5 text-xs leading-4 font-medium">
                                                    {task.taskType.name}
                                                </span>
                                                {task.labels
                                                    .slice(0, MAX_CARD_LABELS)
                                                    .map((label) => (
                                                        <LabelChip
                                                            key={label.id}
                                                            name={label.name}
                                                            color={label.color}
                                                        />
                                                    ))}
                                                {task.labels.length >
                                                MAX_CARD_LABELS ? (
                                                    <span
                                                        className="text-subtle-foreground text-xs"
                                                        title={task.labels
                                                            .slice(
                                                                MAX_CARD_LABELS,
                                                            )
                                                            .map(
                                                                (label) =>
                                                                    label.name,
                                                            )
                                                            .join(', ')}
                                                    >
                                                        +
                                                        {task.labels.length -
                                                            MAX_CARD_LABELS}
                                                    </span>
                                                ) : null}
                                            </div>

                                            <div className="flex min-h-6 items-center justify-between gap-2">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <PriorityIndicator
                                                        value={task.priority}
                                                        label={optionLabel(
                                                            priorityOptions,
                                                            task.priority,
                                                        )}
                                                        showLabel
                                                    />
                                                    {task.due_at ? (
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1 text-xs tabular-nums',
                                                                task.is_overdue
                                                                    ? 'text-destructive-foreground font-medium'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            <CalendarDays
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="sr-only">
                                                                {task.is_overdue
                                                                    ? 'Overdue, due '
                                                                    : 'Due '}
                                                            </span>
                                                            {formatDate(
                                                                task.due_at,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <AvatarStack
                                                    people={task.assignees}
                                                    size="xs"
                                                />
                                            </div>
                                        </div>

                                        {task.can_change_status || canManage ? (
                                            <div className="flex min-w-0 items-center gap-1 border-t px-2 py-1.5 transition-opacity duration-200 md:opacity-0 md:group-focus-within/card:opacity-100 md:group-hover/card:opacity-100">
                                                {task.can_change_status ? (
                                                    <Select
                                                        value={task.status}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            changeColumn(
                                                                task,
                                                                value as TaskStatus,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            size="sm"
                                                            className="hover:bg-accent h-7 min-w-0 flex-1 border-transparent bg-transparent px-2 text-xs shadow-none dark:bg-transparent"
                                                            data-test="task-status-select"
                                                            aria-label={`Status for ${task.reference}`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {statusOptions.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <span className="flex-1" />
                                                )}
                                                {canManage ? (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-xs"
                                                            onClick={() =>
                                                                setAssigningTaskId(
                                                                    task.id,
                                                                )
                                                            }
                                                            data-test="task-assign"
                                                            aria-label="Assign task"
                                                        >
                                                            <Users />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-xs"
                                                            onClick={() =>
                                                                setEditingTask(
                                                                    task,
                                                                )
                                                            }
                                                            data-test="task-edit"
                                                            aria-label="Edit task"
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-xs"
                                                            className="hover:text-destructive"
                                                            onClick={() =>
                                                                setDeletingTask(
                                                                    task,
                                                                )
                                                            }
                                                            data-test="task-delete"
                                                            aria-label="Delete task"
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </article>
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>

            <TaskFormModal
                open={addingToStatus !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setAddingToStatus(null);
                    }
                }}
                projectId={projectId}
                defaultStatus={addingToStatus ?? undefined}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={onChanged}
            />

            <TaskFormModal
                open={editingTask !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingTask(null);
                    }
                }}
                projectId={projectId}
                task={editingTask}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={onChanged}
            />

            <TaskDeleteModal
                projectId={projectId}
                task={deletingTask}
                open={deletingTask !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDeletingTask(null);
                    }
                }}
                onDeleted={onChanged}
            />

            <TaskAssignmentsModal
                projectId={projectId}
                task={assigningTask}
                members={members}
                roleOptions={assignmentRoleOptions}
                open={assigningTaskId !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setAssigningTaskId(null);
                    }
                }}
                onChanged={onChanged}
            />
        </div>
    );
}

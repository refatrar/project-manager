import { Link, useHttp, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import TaskAssignmentsModal from '@/components/projects/task-assignments-modal';
import TaskDeleteModal from '@/components/projects/task-delete-modal';
import TaskFormModal from '@/components/projects/task-form-modal';
import { Badge } from '@/components/ui/badge';
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
                className="flex h-[calc(100svh-16rem)] min-h-96 gap-4 overflow-x-auto overflow-y-hidden pb-3"
                data-test="board-scroll"
            >
                {statusOptions.map((statusOption) => {
                    const column = byStatus.get(statusOption.value) ?? [];

                    return (
                        <div
                            key={statusOption.value}
                            className="bg-muted/40 flex h-full w-72 shrink-0 flex-col rounded-lg border"
                            data-test={`board-column-${statusOption.value}`}
                        >
                            <div className="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                                <h3 className="min-w-0 truncate text-sm font-semibold">
                                    {statusOption.label}{' '}
                                    <span className="text-muted-foreground font-normal">
                                        ({column.length})
                                    </span>
                                </h3>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() =>
                                        setAddingToStatus(statusOption.value)
                                    }
                                    data-test="board-add-task"
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>

                            <div
                                className="min-h-0 flex-1 space-y-2 overflow-y-auto p-2"
                                data-test="board-column-scroll"
                            >
                                {column.map((task, index) => (
                                    <div
                                        key={task.id}
                                        data-test="task-card"
                                        className="bg-card min-w-0 space-y-2 rounded-lg border p-3 shadow-xs"
                                    >
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
                                                className="min-w-0 flex-1"
                                                data-test="task-card-link"
                                            >
                                                <p className="text-muted-foreground truncate font-mono text-xs">
                                                    {task.reference}
                                                </p>
                                                <p className="text-sm font-medium [overflow-wrap:anywhere] hover:underline">
                                                    {task.title}
                                                </p>
                                            </Link>
                                            {task.can_change_status ? (
                                                <div className="flex shrink-0 flex-col gap-0.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 w-6 p-0"
                                                        disabled={index === 0}
                                                        onClick={() =>
                                                            moveWithinColumn(
                                                                task,
                                                                -1,
                                                            )
                                                        }
                                                        data-test="task-move-up"
                                                        aria-label="Move task up"
                                                    >
                                                        <ChevronUp className="h-3 w-3" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 w-6 p-0"
                                                        disabled={
                                                            index ===
                                                            column.length - 1
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
                                                        <ChevronDown className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="flex min-w-0 flex-wrap items-center gap-1">
                                            <Badge
                                                variant="secondary"
                                                className="max-w-full truncate"
                                            >
                                                {task.taskType.name}
                                            </Badge>
                                            <Badge variant="outline">
                                                {task.priority}
                                            </Badge>
                                        </div>

                                        {task.assignees.length > 0 ? (
                                            <p className="text-muted-foreground text-xs [overflow-wrap:anywhere]">
                                                {task.assignees
                                                    .map((a) => a.name)
                                                    .join(', ')}
                                            </p>
                                        ) : null}

                                        {task.can_change_status || canManage ? (
                                            <div className="flex min-w-0 items-center gap-2">
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
                                                            className="h-7 min-w-0 flex-1 text-xs"
                                                            data-test="task-status-select"
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
                                                ) : null}
                                                {canManage ? (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 w-7 p-0"
                                                            onClick={() =>
                                                                setAssigningTaskId(
                                                                    task.id,
                                                                )
                                                            }
                                                            data-test="task-assign"
                                                            aria-label="Assign task"
                                                        >
                                                            <Users className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 w-7 p-0"
                                                            onClick={() =>
                                                                setEditingTask(
                                                                    task,
                                                                )
                                                            }
                                                            data-test="task-edit"
                                                            aria-label="Edit task"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 w-7 p-0"
                                                            onClick={() =>
                                                                setDeletingTask(
                                                                    task,
                                                                )
                                                            }
                                                            data-test="task-delete"
                                                            aria-label="Delete task"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        </div>
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

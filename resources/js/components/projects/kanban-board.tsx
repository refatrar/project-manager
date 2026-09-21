import { Link, useHttp, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2, Users } from 'lucide-react';
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
    TaskDetail,
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
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const moveForm = useHttp<{ status: TaskStatus; position: number }, MovedResponse>({
        status: 'backlog',
        position: 0,
    });

    const [addingToStatus, setAddingToStatus] = useState<TaskStatus | null>(
        null,
    );
    const [editingTask, setEditingTask] = useState<Task | null>(null);
    const [deletingTask, setDeletingTask] = useState<Task | null>(null);
    const [assigningTaskId, setAssigningTaskId] = useState<number | null>(
        null,
    );
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
            column.sort((a, b) => a.position - b.position);
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

    const moveWithinColumn = (task: Task, direction: -1 | 1) => {
        const column = byStatus.get(task.status) ?? [];
        const index = column.findIndex((row) => row.id === task.id);
        const swapWith = column[index + direction];

        if (!swapWith) {
            return;
        }

        moveTask(task, task.status, swapWith.position);
    };

    const changeColumn = (task: Task, status: TaskStatus) => {
        const column = byStatus.get(status) ?? [];
        const nextPosition =
            column.length > 0
                ? Math.max(...column.map((row) => row.position)) + 1
                : 0;

        moveTask(task, status, nextPosition);
    };

    return (
        <div className="space-y-4">
            <div className="flex gap-4 overflow-x-auto pb-4">
                {statusOptions.map((statusOption) => {
                    const column = byStatus.get(statusOption.value) ?? [];

                    return (
                        <div
                            key={statusOption.value}
                            className="w-72 shrink-0 space-y-3"
                            data-test={`board-column-${statusOption.value}`}
                        >
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">
                                    {statusOption.label}{' '}
                                    <span className="text-muted-foreground font-normal">
                                        ({column.length})
                                    </span>
                                </h3>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setAddingToStatus(statusOption.value)
                                    }
                                    data-test="board-add-task"
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {column.map((task, index) => (
                                    <div
                                        key={task.id}
                                        data-test="task-card"
                                        className="space-y-2 rounded-lg border p-3"
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
                                                className="min-w-0"
                                                data-test="task-card-link"
                                            >
                                                <p className="text-muted-foreground font-mono text-xs">
                                                    {task.reference}
                                                </p>
                                                <p className="text-sm font-medium hover:underline">
                                                    {task.title}
                                                </p>
                                            </Link>
                                            <div className="flex shrink-0 flex-col gap-0.5">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-6 w-6 p-0"
                                                    disabled={index === 0}
                                                    onClick={() =>
                                                        moveWithinColumn(task, -1)
                                                    }
                                                    data-test="task-move-up"
                                                >
                                                    <ChevronUp className="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-6 w-6 p-0"
                                                    disabled={
                                                        index === column.length - 1
                                                    }
                                                    onClick={() =>
                                                        moveWithinColumn(task, 1)
                                                    }
                                                    data-test="task-move-down"
                                                >
                                                    <ChevronDown className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1">
                                            <Badge variant="secondary">
                                                {task.taskType.name}
                                            </Badge>
                                            <Badge variant="outline">
                                                {task.priority}
                                            </Badge>
                                        </div>

                                        {task.assignees.length > 0 ? (
                                            <p className="text-muted-foreground text-xs">
                                                {task.assignees
                                                    .map((a) => a.name)
                                                    .join(', ')}
                                            </p>
                                        ) : null}

                                        <div className="flex items-center gap-2">
                                            <Select
                                                value={task.status}
                                                onValueChange={(value) =>
                                                    changeColumn(
                                                        task,
                                                        value as TaskStatus,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    className="h-7 flex-1 text-xs"
                                                    data-test="task-status-select"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statusOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={option.value}
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={() =>
                                                    setAssigningTaskId(task.id)
                                                }
                                                data-test="task-assign"
                                            >
                                                <Users className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={() =>
                                                    setEditingTask(task)
                                                }
                                                data-test="task-edit"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={() =>
                                                    setDeletingTask(task)
                                                }
                                                data-test="task-delete"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
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

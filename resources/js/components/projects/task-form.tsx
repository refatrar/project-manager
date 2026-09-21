import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    Priority,
    PriorityOption,
    ProjectModule,
    SprintOption,
    Task,
    TaskDetail,
    TaskLabel,
    TaskStatus,
    TaskStatusOption,
    TaskTypeOption,
} from '@/types';

export type TaskFormData = {
    title: string;
    description: string;
    task_type_id: string;
    project_module_id: string;
    milestone_id: string;
    sprint_id: string;
    parent_id: string;
    status: TaskStatus;
    priority: Priority;
    estimated_hours: string;
    remaining_hours: string;
    is_billable: boolean;
    starts_at: string;
    due_at: string;
    label_ids: number[];
};

export type TaskSavedResponse = {
    task: TaskDetail;
    message: string;
};

type Props = {
    projectId: number;
    task?: Task | TaskDetail | null;
    defaultStatus?: TaskStatus;
    defaultParentId?: number;
    taskTypes: TaskTypeOption[];
    modules: ProjectModule[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
    statusOptions: TaskStatusOption[];
    priorityOptions: PriorityOption[];
    onSaved?: (task: TaskDetail, message: string) => void;
    onCancel?: () => void;
};

function isDetail(task: Task | TaskDetail | null | undefined): task is TaskDetail {
    return task !== null && task !== undefined && 'description' in task;
}

export default function TaskForm({
    projectId,
    task = null,
    defaultStatus = 'backlog',
    defaultParentId,
    taskTypes,
    modules,
    milestones,
    sprints,
    labels,
    statusOptions,
    priorityOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const detail = isDetail(task) ? task : null;

    const form = useHttp<TaskFormData, TaskSavedResponse>(
        () =>
            task
                ? update.put([teamSlug ?? '', projectId, task.id])
                : store([teamSlug ?? '', projectId]),
        {
            title: task?.title ?? '',
            description: detail?.description ?? '',
            task_type_id: task ? String(task.taskType.id) : '',
            project_module_id: task?.project_module_id
                ? String(task.project_module_id)
                : 'none',
            milestone_id: task?.milestone_id ? String(task.milestone_id) : 'none',
            sprint_id: task?.sprint_id ? String(task.sprint_id) : 'none',
            parent_id: defaultParentId ? String(defaultParentId) : 'none',
            status: task?.status ?? defaultStatus,
            priority: task?.priority ?? 'medium',
            estimated_hours: detail?.estimated_hours ?? '',
            remaining_hours: detail?.remaining_hours ?? '',
            is_billable: detail?.is_billable ?? true,
            starts_at: detail?.starts_at?.slice(0, 10) ?? '',
            due_at: task?.due_at?.slice(0, 10) ?? '',
            label_ids: task?.labels.map((l) => l.id) ?? [],
        },
    );

    const toggleLabel = (labelId: number) => {
        const current = form.data.label_ids;
        form.setData(
            'label_ids',
            current.includes(labelId)
                ? current.filter((id) => id !== labelId)
                : [...current, labelId],
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.task, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="task-title">Title</Label>
                <Input
                    id="task-title"
                    value={form.data.title}
                    onChange={(event) => form.setData('title', event.target.value)}
                    placeholder="Set up CI pipeline"
                    required
                    data-test="task-title"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="task-description">Description</Label>
                <Textarea
                    id="task-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="task-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="task-type">Type</Label>
                    <Select
                        value={form.data.task_type_id}
                        onValueChange={(value) =>
                            form.setData('task_type_id', value)
                        }
                    >
                        <SelectTrigger id="task-type" data-test="task-type">
                            <SelectValue placeholder="Select a type" />
                        </SelectTrigger>
                        <SelectContent>
                            {taskTypes.map((type) => (
                                <SelectItem key={type.id} value={String(type.id)}>
                                    {type.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.task_type_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="task-module">Module</Label>
                    <Select
                        value={form.data.project_module_id}
                        onValueChange={(value) =>
                            form.setData('project_module_id', value)
                        }
                    >
                        <SelectTrigger id="task-module" data-test="task-module">
                            <SelectValue placeholder="No module" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No module</SelectItem>
                            {modules.map((module) => (
                                <SelectItem
                                    key={module.id}
                                    value={String(module.id)}
                                >
                                    {module.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.project_module_id} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="task-milestone">Milestone</Label>
                    <Select
                        value={form.data.milestone_id}
                        onValueChange={(value) =>
                            form.setData('milestone_id', value)
                        }
                    >
                        <SelectTrigger
                            id="task-milestone"
                            data-test="task-milestone"
                        >
                            <SelectValue placeholder="No milestone" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No milestone</SelectItem>
                            {milestones.map((milestone) => (
                                <SelectItem
                                    key={milestone.id}
                                    value={String(milestone.id)}
                                >
                                    {milestone.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.milestone_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="task-sprint">Sprint</Label>
                    <Select
                        value={form.data.sprint_id}
                        onValueChange={(value) =>
                            form.setData('sprint_id', value)
                        }
                    >
                        <SelectTrigger id="task-sprint" data-test="task-sprint">
                            <SelectValue placeholder="No sprint" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No sprint</SelectItem>
                            {sprints.map((sprint) => (
                                <SelectItem
                                    key={sprint.id}
                                    value={String(sprint.id)}
                                >
                                    {sprint.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.sprint_id} />
                </div>
            </div>

            {labels.length > 0 ? (
                <div className="grid gap-2">
                    <Label>Labels</Label>
                    <div className="flex flex-wrap gap-2">
                        {labels.map((label) => {
                            const selected = form.data.label_ids.includes(
                                label.id,
                            );

                            return (
                                <button
                                    key={label.id}
                                    type="button"
                                    onClick={() => toggleLabel(label.id)}
                                    data-test="task-label-toggle"
                                    className={cn(
                                        'transition-opacity',
                                        !selected && 'opacity-50',
                                    )}
                                >
                                    <Badge
                                        variant={selected ? 'default' : 'outline'}
                                        style={
                                            selected && label.color
                                                ? {
                                                      backgroundColor:
                                                          label.color,
                                                  }
                                                : undefined
                                        }
                                    >
                                        {label.name}
                                    </Badge>
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={form.errors.label_ids} />
                </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
                {!task ? (
                    <div className="grid gap-2">
                        <Label htmlFor="task-status">Status</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData('status', value as TaskStatus)
                            }
                        >
                            <SelectTrigger id="task-status" data-test="task-status">
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>
                ) : null}

                <div className="grid gap-2">
                    <Label htmlFor="task-priority">Priority</Label>
                    <Select
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value as Priority)
                        }
                    >
                        <SelectTrigger id="task-priority" data-test="task-priority">
                            <SelectValue placeholder="Select a priority" />
                        </SelectTrigger>
                        <SelectContent>
                            {priorityOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.priority} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="task-starts-at">Start date</Label>
                    <Input
                        id="task-starts-at"
                        type="date"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                    <InputError message={form.errors.starts_at} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="task-due-at">Due date</Label>
                    <Input
                        id="task-due-at"
                        type="date"
                        value={form.data.due_at}
                        onChange={(event) =>
                            form.setData('due_at', event.target.value)
                        }
                    />
                    <InputError message={form.errors.due_at} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3 sm:items-end">
                <div className="grid gap-2">
                    <Label htmlFor="task-estimated-hours">Est. hours</Label>
                    <Input
                        id="task-estimated-hours"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.estimated_hours}
                        onChange={(event) =>
                            form.setData('estimated_hours', event.target.value)
                        }
                    />
                    <InputError message={form.errors.estimated_hours} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="task-remaining-hours">Remaining hours</Label>
                    <Input
                        id="task-remaining-hours"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.remaining_hours}
                        onChange={(event) =>
                            form.setData('remaining_hours', event.target.value)
                        }
                    />
                    <InputError message={form.errors.remaining_hours} />
                </div>

                <div className="flex items-center gap-2 pb-2">
                    <Checkbox
                        id="task-billable"
                        checked={form.data.is_billable}
                        onCheckedChange={(checked) =>
                            form.setData('is_billable', checked === true)
                        }
                    />
                    <Label htmlFor="task-billable">Billable</Label>
                </div>
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="task-submit"
                >
                    {task ? 'Save changes' : 'Create task'}
                </Button>
            </div>
        </form>
    );
}

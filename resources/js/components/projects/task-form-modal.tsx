import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TaskForm from '@/components/projects/task-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    MilestoneOption,
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

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
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
    onSaved?: (task: TaskDetail) => void;
}>;

export default function TaskFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    task = null,
    defaultStatus,
    defaultParentId,
    taskTypes,
    modules,
    milestones,
    sprints,
    labels,
    statusOptions,
    priorityOptions,
    onSaved,
}: Props) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isControlled = open !== undefined;
    const dialogOpen = isControlled ? open : uncontrolledOpen;

    const setDialogOpen = (nextOpen: boolean) => {
        if (!isControlled) {
            setUncontrolledOpen(nextOpen);
        }

        onOpenChange?.(nextOpen);
    };

    const isEditing = Boolean(task);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? 'Edit task'
                            : defaultParentId
                              ? 'Create subtask'
                              : 'Create task'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this task.'
                            : defaultParentId
                              ? 'Add a subtask under this task.'
                              : 'Add a task to the project.'}
                    </DialogDescription>
                </DialogHeader>

                <TaskForm
                    key={`${String(dialogOpen)}-${task?.id ?? 'create'}-${defaultStatus ?? 'backlog'}-${defaultParentId ?? 'none'}`}
                    projectId={projectId}
                    task={task}
                    defaultStatus={defaultStatus}
                    defaultParentId={defaultParentId}
                    taskTypes={taskTypes}
                    modules={modules}
                    milestones={milestones}
                    sprints={sprints}
                    labels={labels}
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedTask, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedTask);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

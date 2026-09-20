import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TaskTypeForm from '@/components/setup/task-type-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { ScopeStatusOption, TaskType } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    taskType?: TaskType | null;
    statusOptions?: ScopeStatusOption[];
    onSaved?: (taskType: TaskType) => void;
}>;

export default function TaskTypeFormModal({
    children,
    open,
    onOpenChange,
    taskType = null,
    statusOptions,
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

    const isEditing = Boolean(taskType);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit task type' : 'Create task type'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the name, description, and status for this task type.'
                            : 'Add a task type that can be reused across projects and other forms.'}
                    </DialogDescription>
                </DialogHeader>

                <TaskTypeForm
                    key={`${String(dialogOpen)}-${taskType?.id ?? 'create'}`}
                    taskType={taskType}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedTaskType, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedTaskType);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

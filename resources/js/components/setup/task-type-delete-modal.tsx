import { useHttp, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/setup/task-types';
import type { TaskType } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    taskType: TaskType | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (taskType: TaskType) => void;
};

export default function TaskTypeDeleteModal({
    taskType,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteTaskType = () => {
        if (!taskType || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, taskType.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(taskType);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete task type</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{taskType?.name}</strong>? It will no longer
                        appear in the active list.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="task-type-delete-confirm"
                        disabled={form.processing || !taskType}
                        onClick={deleteTaskType}
                    >
                        Delete task type
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

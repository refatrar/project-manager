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
import { destroy } from '@/routes/projects/tasks';
import type { Task } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    task: Task | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (task: Task) => void;
};

export default function TaskDeleteModal({
    projectId,
    task,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteTask = () => {
        if (!task || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, task.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(task);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete task</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>
                            {task?.reference} — {task?.title}
                        </strong>
                        ?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="task-delete-confirm"
                        disabled={form.processing || !task}
                        onClick={deleteTask}
                    >
                        Delete task
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

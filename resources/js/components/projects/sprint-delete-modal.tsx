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
import { destroy } from '@/routes/projects/sprints';
import type { Sprint } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    sprint: Sprint | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (sprint: Sprint) => void;
};

export default function SprintDeleteModal({
    projectId,
    sprint,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteSprint = () => {
        if (!sprint || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, sprint.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(sprint);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete sprint</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{sprint?.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="sprint-delete-confirm"
                        disabled={form.processing || !sprint}
                        onClick={deleteSprint}
                    >
                        Delete sprint
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

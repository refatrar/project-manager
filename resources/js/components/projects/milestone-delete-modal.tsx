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
import { destroy } from '@/routes/projects/milestones';
import type { Milestone } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    milestone: Milestone | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (milestone: Milestone) => void;
};

export default function MilestoneDeleteModal({
    projectId,
    milestone,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteMilestone = () => {
        if (!milestone || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, milestone.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(milestone);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete milestone</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{milestone?.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="milestone-delete-confirm"
                        disabled={form.processing || !milestone}
                        onClick={deleteMilestone}
                    >
                        Delete milestone
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

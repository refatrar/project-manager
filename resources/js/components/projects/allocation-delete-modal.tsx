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
import { destroy } from '@/routes/projects/resource-allocations';
import type { ResourceAllocation } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    allocation: ResourceAllocation | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (allocation: ResourceAllocation) => void;
};

export default function AllocationDeleteModal({
    projectId,
    allocation,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteAllocation = () => {
        if (!allocation || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, allocation.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(allocation);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete booking</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this booking for{' '}
                        <strong>{allocation?.user.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="allocation-delete-confirm"
                        disabled={form.processing || !allocation}
                        onClick={deleteAllocation}
                    >
                        Delete booking
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

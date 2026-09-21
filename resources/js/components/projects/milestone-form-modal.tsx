import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import MilestoneForm from '@/components/projects/milestone-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Milestone, MilestoneStatusOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    projectId: number;
    milestone?: Milestone | null;
    statusOptions: MilestoneStatusOption[];
    onSaved?: (milestone: Milestone) => void;
}>;

export default function MilestoneFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    milestone = null,
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

    const isEditing = Boolean(milestone);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit milestone' : 'Create milestone'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this milestone.'
                            : 'Add a dated checkpoint to the project.'}
                    </DialogDescription>
                </DialogHeader>

                <MilestoneForm
                    key={`${String(dialogOpen)}-${milestone?.id ?? 'create'}`}
                    projectId={projectId}
                    milestone={milestone}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedMilestone, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedMilestone);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

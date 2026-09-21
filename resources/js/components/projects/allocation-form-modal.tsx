import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import AllocationForm from '@/components/projects/allocation-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { AllocationStatusOption, ProjectMember, ResourceAllocation } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    projectId: number;
    allocation?: ResourceAllocation | null;
    members: ProjectMember[];
    statusOptions: AllocationStatusOption[];
    onSaved?: (allocation: ResourceAllocation) => void;
}>;

export default function AllocationFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    allocation = null,
    members,
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

    const isEditing = Boolean(allocation);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? <DialogTrigger asChild>{children}</DialogTrigger> : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit booking' : 'Book hours'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this forward booking.'
                            : "Reserve a member's hours against this project."}
                    </DialogDescription>
                </DialogHeader>

                <AllocationForm
                    key={`${String(dialogOpen)}-${allocation?.id ?? 'create'}`}
                    projectId={projectId}
                    allocation={allocation}
                    members={members}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedAllocation, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedAllocation);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

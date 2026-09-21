import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import SprintForm from '@/components/projects/sprint-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Sprint, SprintStatusOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    projectId: number;
    sprint?: Sprint | null;
    statusOptions: SprintStatusOption[];
    onSaved?: (sprint: Sprint) => void;
}>;

export default function SprintFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    sprint = null,
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

    const isEditing = Boolean(sprint);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit sprint' : 'Create sprint'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this sprint.'
                            : 'Add a time-boxed iteration to the project.'}
                    </DialogDescription>
                </DialogHeader>

                <SprintForm
                    key={`${String(dialogOpen)}-${sprint?.id ?? 'create'}`}
                    projectId={projectId}
                    sprint={sprint}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedSprint, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedSprint);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

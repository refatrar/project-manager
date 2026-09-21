import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import LabelForm from '@/components/setup/label-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Label } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    label?: Label | null;
    onSaved?: (label: Label) => void;
}>;

export default function LabelFormModal({
    children,
    open,
    onOpenChange,
    label = null,
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

    const isEditing = Boolean(label);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit label' : 'Create label'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the name, color, and description for this label.'
                            : 'Add a label that can be applied to tasks across the team.'}
                    </DialogDescription>
                </DialogHeader>

                <LabelForm
                    key={`${String(dialogOpen)}-${label?.id ?? 'create'}`}
                    label={label}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedLabel, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedLabel);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

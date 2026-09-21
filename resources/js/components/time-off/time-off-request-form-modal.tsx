import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TimeOffRequestForm from '@/components/time-off/time-off-request-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { TimeOffRequest, TimeOffTypeOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    request?: TimeOffRequest | null;
    typeOptions: TimeOffTypeOption[];
    onSaved?: () => void;
}>;

export default function TimeOffRequestFormModal({
    children,
    open,
    onOpenChange,
    request = null,
    typeOptions,
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

    const isEditing = Boolean(request);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? <DialogTrigger asChild>{children}</DialogTrigger> : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit time off request' : 'Request time off'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update your request while it is still pending.'
                            : 'Approved time off reduces your available capacity; pending requests do not.'}
                    </DialogDescription>
                </DialogHeader>

                <TimeOffRequestForm
                    key={`${String(dialogOpen)}-${request?.id ?? 'create'}`}
                    request={request}
                    typeOptions={typeOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.();
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

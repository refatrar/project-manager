import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TimeLogForm from '@/components/time-logs/time-log-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { ProjectOption, TimeLog, TimeLogActivityTypeOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    log?: TimeLog | null;
    projects: ProjectOption[];
    activityTypeOptions: TimeLogActivityTypeOption[];
    onSaved?: () => void;
}>;

export default function TimeLogFormModal({
    children,
    open,
    onOpenChange,
    log = null,
    projects,
    activityTypeOptions,
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

    const isEditing = Boolean(log);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? <DialogTrigger asChild>{children}</DialogTrigger> : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit time log' : 'Log time'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this entry while it is still pending.'
                            : 'Record time already worked, separate from planned bookings.'}
                    </DialogDescription>
                </DialogHeader>

                <TimeLogForm
                    key={`${String(dialogOpen)}-${log?.id ?? 'create'}`}
                    log={log}
                    projects={projects}
                    activityTypeOptions={activityTypeOptions}
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

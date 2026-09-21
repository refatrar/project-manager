import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import MeetingForm from '@/components/meetings/meeting-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    Meeting,
    MeetingDetail,
    MeetingTypeOption,
    ProjectOption,
} from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    meeting?: Meeting | MeetingDetail | null;
    typeOptions: MeetingTypeOption[];
    projects: ProjectOption[];
    onSaved?: (meeting: MeetingDetail) => void;
}>;

export default function MeetingFormModal({
    children,
    open,
    onOpenChange,
    meeting = null,
    typeOptions,
    projects,
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

    const isEditing = Boolean(meeting);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit meeting' : 'Schedule meeting'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this meeting.'
                            : 'Set up a new meeting for this team.'}
                    </DialogDescription>
                </DialogHeader>

                <MeetingForm
                    key={`${String(dialogOpen)}-${meeting?.id ?? 'create'}`}
                    meeting={meeting}
                    typeOptions={typeOptions}
                    projects={projects}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedMeeting, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedMeeting);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

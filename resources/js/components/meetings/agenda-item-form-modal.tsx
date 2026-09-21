import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import AgendaItemForm from '@/components/meetings/agenda-item-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { MeetingAgendaItem, TaskReference, TeamMemberOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    meetingId: number;
    item?: MeetingAgendaItem | null;
    projectTasks: TaskReference[];
    teamMembers: TeamMemberOption[];
    onSaved?: (item: MeetingAgendaItem) => void;
}>;

export default function AgendaItemFormModal({
    children,
    open,
    onOpenChange,
    meetingId,
    item = null,
    projectTasks,
    teamMembers,
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

    const isEditing = Boolean(item);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit agenda item' : 'Add agenda item'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this agenda item.'
                            : 'Add an item to the meeting agenda.'}
                    </DialogDescription>
                </DialogHeader>

                <AgendaItemForm
                    key={`${String(dialogOpen)}-${item?.id ?? 'create'}`}
                    meetingId={meetingId}
                    item={item}
                    projectTasks={projectTasks}
                    teamMembers={teamMembers}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedItem, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedItem);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

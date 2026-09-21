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
import { destroy } from '@/routes/meetings';
import type { Meeting, MeetingDetail } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    meeting: Meeting | MeetingDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: () => void;
};

export default function MeetingDeleteModal({
    meeting,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteMeeting = () => {
        if (!meeting || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, meeting.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete meeting</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{meeting?.title}</strong>? This cannot be
                        undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="meeting-delete-confirm"
                        disabled={form.processing || !meeting}
                        onClick={deleteMeeting}
                    >
                        Delete meeting
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

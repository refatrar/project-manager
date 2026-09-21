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
import { cancel } from '@/routes/meetings';
import type { Meeting, MeetingDetail } from '@/types';

type CancelledResponse = {
    meeting: MeetingDetail;
    message: string;
};

type Props = {
    meeting: Meeting | MeetingDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCancelled?: (meeting: MeetingDetail) => void;
};

export default function MeetingCancelModal({
    meeting,
    open,
    onOpenChange,
    onCancelled,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, CancelledResponse>({});

    const cancelMeeting = () => {
        if (!meeting || !teamSlug) {
            return;
        }

        void form.patch(cancel.url([teamSlug, meeting.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onCancelled?.(response.meeting);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel meeting</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to cancel{' '}
                        <strong>{meeting?.title}</strong>? Its history stays
                        intact, but it will be marked cancelled.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Keep meeting</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="meeting-cancel-confirm"
                        disabled={form.processing || !meeting}
                        onClick={cancelMeeting}
                    >
                        Cancel meeting
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

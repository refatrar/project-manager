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
import { decide } from '@/routes/timesheet-approvals';
import type { TimeLog } from '@/types';

type DecidedResponse = {
    message: string;
};

type Props = {
    entry: TimeLog | null;
    decision: 'approved' | 'rejected';
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDecided?: () => void;
};

export default function DecideTimeLogModal({ entry, decision, open, onOpenChange, onDecided }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<{ decision: string }, DecidedResponse>({ decision });

    const submit = () => {
        if (!entry || !teamSlug) {
            return;
        }

        form.transform(() => ({ decision }));

        void form.patch(decide.url([teamSlug, entry.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDecided?.();
            },
        });
    };

    const isApproving = decision === 'approved';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isApproving ? 'Approve time log' : 'Reject time log'}</DialogTitle>
                    <DialogDescription>
                        {entry?.user.name} · {entry?.logged_on} ·{' '}
                        {entry ? Math.round((entry.duration_minutes / 60) * 100) / 100 : 0}h
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant={isApproving ? 'default' : 'destructive'}
                        disabled={form.processing || !entry || !teamSlug}
                        onClick={submit}
                        data-test="timesheet-decision-confirm"
                    >
                        {isApproving ? 'Approve' : 'Reject'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

import { useHttp, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { decide } from '@/routes/time-off-requests';
import type { TimeOffRequest } from '@/types';

type DecidedResponse = {
    message: string;
};

type Props = {
    request: TimeOffRequest | null;
    decision: 'approved' | 'rejected';
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDecided?: () => void;
};

export default function DecideTimeOffRequestModal({
    request,
    decision,
    open,
    onOpenChange,
    onDecided,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [note, setNote] = useState('');
    const form = useHttp<{ decision: string; decision_note: string }, DecidedResponse>({
        decision,
        decision_note: '',
    });

    const submit = () => {
        if (!request || !teamSlug) {
            return;
        }

        form.transform(() => ({ decision, decision_note: note }));

        void form.patch(decide.url([teamSlug, request.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                setNote('');
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
                    <DialogTitle>
                        {isApproving ? 'Approve time off' : 'Reject time off'}
                    </DialogTitle>
                    <DialogDescription>
                        {request?.user.name} · {request?.starts_on} – {request?.ends_on}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="decision-note">Note (optional)</Label>
                    <Textarea
                        id="decision-note"
                        rows={3}
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        data-test="decision-note"
                    />
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant={isApproving ? 'default' : 'destructive'}
                        disabled={form.processing || !request || !teamSlug}
                        onClick={submit}
                        data-test="decision-confirm"
                    >
                        {isApproving ? 'Approve' : 'Reject'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

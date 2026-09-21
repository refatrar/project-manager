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
import { destroy } from '@/routes/setup/labels';
import type { Label } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    label: Label | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (label: Label) => void;
};

export default function LabelDeleteModal({
    label,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteLabel = () => {
        if (!label || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, label.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(label);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete label</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{label?.name}</strong>? It will be removed from
                        every task it's applied to. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="label-delete-confirm"
                        disabled={form.processing || !label}
                        onClick={deleteLabel}
                    >
                        Delete label
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

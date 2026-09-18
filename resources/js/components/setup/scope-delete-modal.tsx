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
import { destroy } from '@/routes/setup/scopes';
import type { Scope } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    scope: Scope | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (scope: Scope) => void;
};

export default function ScopeDeleteModal({
    scope,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteScope = () => {
        if (!scope || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, scope.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(scope);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete scope</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{scope?.name}</strong>? It will no longer appear
                        in the active list.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="scope-delete-confirm"
                        disabled={form.processing || !scope}
                        onClick={deleteScope}
                    >
                        Delete scope
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

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
import { destroy } from '@/routes/todo-lists';
import type { TodoList } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    list: TodoList | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (list: TodoList) => void;
};

export default function TodoListDeleteModal({
    list,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteList = () => {
        if (!list || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, list.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(list);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete list</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{list?.name}</strong>? Its items will be
                        deleted too. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="todo-list-delete-confirm"
                        disabled={form.processing || !list}
                        onClick={deleteList}
                    >
                        Delete list
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

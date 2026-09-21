import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TodoItemForm from '@/components/todo-lists/todo-item-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { TeamMemberOption, TodoItem } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    todoListId: number;
    item?: TodoItem | null;
    teamMembers: TeamMemberOption[];
    onSaved?: (item: TodoItem) => void;
}>;

export default function TodoItemFormModal({
    children,
    open,
    onOpenChange,
    todoListId,
    item = null,
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
                        {isEditing ? 'Edit item' : 'Add item'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this item.'
                            : 'Add an item to this list.'}
                    </DialogDescription>
                </DialogHeader>

                <TodoItemForm
                    key={`${String(dialogOpen)}-${item?.id ?? 'create'}`}
                    todoListId={todoListId}
                    item={item}
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

import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import TodoListForm from '@/components/todo-lists/todo-list-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { TodoList, TodoListStatusOption, TodoListTypeOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    list?: TodoList | null;
    typeOptions: TodoListTypeOption[];
    statusOptions: TodoListStatusOption[];
    onSaved?: (list: TodoList) => void;
}>;

export default function TodoListFormModal({
    children,
    open,
    onOpenChange,
    list = null,
    typeOptions,
    statusOptions,
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

    const isEditing = Boolean(list);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit list' : 'Create list'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this list.'
                            : 'Personal to-dos, or a plan for a single day.'}
                    </DialogDescription>
                </DialogHeader>

                <TodoListForm
                    key={`${String(dialogOpen)}-${list?.id ?? 'create'}`}
                    list={list}
                    typeOptions={typeOptions}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedList, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedList);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

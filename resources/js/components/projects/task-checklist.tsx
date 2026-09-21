import { Link, useHttp, usePage } from '@inertiajs/react';
import { ArrowUpRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import PromoteTodoItemModal from '@/components/todo-lists/promote-todo-item-modal';
import TodoItemFormModal from '@/components/todo-lists/todo-item-form-modal';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { show as showTask } from '@/routes/projects/tasks';
import { destroy as destroyItem, toggle } from '@/routes/todo-lists/items';
import type { TaskTypeOption, TeamMemberOption, TodoItem, TodoList } from '@/types';

type ToggledResponse = {
    item: TodoItem;
};

type Props = {
    checklist: TodoList;
    projectMembers: TeamMemberOption[];
    taskTypes: TaskTypeOption[];
    onChanged: () => void;
};

export default function TaskChecklist({
    checklist,
    projectMembers,
    taskTypes,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editingItem, setEditingItem] = useState<TodoItem | null>(null);
    const itemForm = useHttp<{ is_completed: boolean }, ToggledResponse>({
        is_completed: false,
    });

    const toggleItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        itemForm.transform(() => ({ is_completed: !item.is_completed }));

        void itemForm.patch(toggle.url([teamSlug, checklist.id, item.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that item.'),
        });
    };

    const deleteItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        void itemForm.delete(
            destroyItem.url([teamSlug, checklist.id, item.id]),
            {
                onSuccess: () => {
                    toast.success('Item removed.');
                    onChanged();
                },
            },
        );
    };

    const completed = checklist.items.filter((item) => item.is_completed).length;

    return (
        <div className="space-y-3">
            {checklist.items.length > 0 ? (
                <>
                    <p className="text-muted-foreground text-sm">
                        {completed}/{checklist.items.length} complete
                    </p>
                    <ul className="space-y-2">
                        {checklist.items.map((item) => (
                            <li
                                key={item.id}
                                data-test="checklist-item-row"
                                className="flex items-start gap-3 rounded-lg border p-3"
                            >
                                <Checkbox
                                    checked={item.is_completed}
                                    onCheckedChange={() => toggleItem(item)}
                                    data-test="checklist-item-toggle"
                                    className="mt-0.5"
                                />

                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'text-sm',
                                            item.is_completed &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {item.title}
                                    </p>
                                    {item.assignee ? (
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            {item.assignee.name}
                                        </p>
                                    ) : null}
                                    {item.task && teamSlug ? (
                                        <Link
                                            href={showTask.url([
                                                teamSlug,
                                                item.task.project_id,
                                                item.task.id,
                                            ])}
                                            data-test="checklist-item-task-link"
                                            className="text-primary mt-1 inline-flex items-center gap-1 text-xs hover:underline"
                                        >
                                            <ArrowUpRight className="h-3 w-3" />
                                            {item.task.reference}
                                        </Link>
                                    ) : null}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {!item.task ? (
                                        <PromoteTodoItemModal
                                            todoListId={checklist.id}
                                            item={item}
                                            taskTypes={taskTypes}
                                            onPromoted={onChanged}
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                data-test="checklist-item-promote"
                                            >
                                                <ArrowUpRight className="h-3.5 w-3.5" />
                                            </Button>
                                        </PromoteTodoItemModal>
                                    ) : null}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="checklist-item-edit"
                                        onClick={() => setEditingItem(item)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="checklist-item-delete"
                                        onClick={() => deleteItem(item)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <p className="text-muted-foreground text-sm">
                    No checklist items yet.
                </p>
            )}

            <TodoItemFormModal
                todoListId={checklist.id}
                teamMembers={projectMembers}
                onSaved={onChanged}
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="checklist-item-add"
                >
                    <Plus className="h-4 w-4" /> Add item
                </Button>
            </TodoItemFormModal>

            <TodoItemFormModal
                open={editingItem !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingItem(null);
                    }
                }}
                todoListId={checklist.id}
                item={editingItem}
                teamMembers={projectMembers}
                onSaved={onChanged}
            />
        </div>
    );
}

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
import type {
    ProjectOption,
    TaskTypeOption,
    TeamMemberOption,
    TodoItem,
    TodoList,
} from '@/types';

type ToggledResponse = {
    item: TodoItem;
};

type Props = {
    actionList: TodoList;
    teamMembers: TeamMemberOption[];
    projects: ProjectOption[];
    taskTypes: TaskTypeOption[];
    onChanged: () => void;
};

export default function MeetingActionItems({
    actionList,
    teamMembers,
    projects,
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

        void itemForm.patch(toggle.url([teamSlug, actionList.id, item.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that item.'),
        });
    };

    const deleteItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        void itemForm.delete(
            destroyItem.url([teamSlug, actionList.id, item.id]),
            {
                onSuccess: () => {
                    toast.success('Item removed.');
                    onChanged();
                },
            },
        );
    };

    return (
        <div className="space-y-3">
            {actionList.items.length > 0 ? (
                <ul className="space-y-2">
                    {actionList.items.map((item) => (
                        <li
                            key={item.id}
                            data-test="action-item-row"
                            className="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                checked={item.is_completed}
                                onCheckedChange={() => toggleItem(item)}
                                data-test="action-item-toggle"
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
                                        data-test="action-item-task-link"
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
                                        todoListId={actionList.id}
                                        item={item}
                                        taskTypes={taskTypes}
                                        projects={projects}
                                        onPromoted={onChanged}
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 w-7 p-0"
                                            data-test="action-item-promote"
                                        >
                                            <ArrowUpRight className="h-3.5 w-3.5" />
                                        </Button>
                                    </PromoteTodoItemModal>
                                ) : null}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="action-item-edit"
                                    onClick={() => setEditingItem(item)}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="action-item-delete"
                                    onClick={() => deleteItem(item)}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-muted-foreground text-sm">
                    No action items yet.
                </p>
            )}

            <TodoItemFormModal
                todoListId={actionList.id}
                teamMembers={teamMembers}
                onSaved={onChanged}
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="action-item-add"
                >
                    <Plus className="h-4 w-4" /> Add action item
                </Button>
            </TodoItemFormModal>

            <TodoItemFormModal
                open={editingItem !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingItem(null);
                    }
                }}
                todoListId={actionList.id}
                item={editingItem}
                teamMembers={teamMembers}
                onSaved={onChanged}
            />
        </div>
    );
}

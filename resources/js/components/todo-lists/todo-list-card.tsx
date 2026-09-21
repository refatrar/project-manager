import { Link, useHttp, usePage } from '@inertiajs/react';
import { ArrowUpRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import PromoteTodoItemModal from '@/components/todo-lists/promote-todo-item-modal';
import TodoItemFormModal from '@/components/todo-lists/todo-item-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    list: TodoList;
    teamMembers: TeamMemberOption[];
    projects: ProjectOption[];
    taskTypes: TaskTypeOption[];
    onEditList: () => void;
    onDeleteList: () => void;
    onChanged: () => void;
};

export default function TodoListCard({
    list,
    teamMembers,
    projects,
    taskTypes,
    onEditList,
    onDeleteList,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editingItem, setEditingItem] = useState<TodoItem | null>(null);
    const toggleForm = useHttp<{ is_completed: boolean }, ToggledResponse>({
        is_completed: false,
    });

    const toggleItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        toggleForm.transform(() => ({ is_completed: !item.is_completed }));

        void toggleForm.patch(toggle.url([teamSlug, list.id, item.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that item.'),
        });
    };

    const deleteItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        void toggleForm.delete(
            destroyItem.url([teamSlug, list.id, item.id]),
            {
                onSuccess: () => {
                    toast.success('Item removed.');
                    onChanged();
                },
            },
        );
    };

    const sortedItems = [...list.items].sort(
        (a, b) => Number(a.is_completed) - Number(b.is_completed),
    );

    return (
        <Card data-test="todo-list-card">
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="min-w-0">
                    <CardTitle className="flex items-center gap-2">
                        {list.name}
                        {list.type === 'daily' ? (
                            <Badge variant="secondary">
                                {list.scheduled_for}
                            </Badge>
                        ) : null}
                        {list.type === 'generated' ? (
                            <Badge variant="secondary" data-test="todo-list-generated-badge">
                                Generated
                            </Badge>
                        ) : null}
                        {list.status !== 'open' ? (
                            <Badge variant="outline">{list.status}</Badge>
                        ) : null}
                    </CardTitle>
                    {list.description ? (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {list.description}
                        </p>
                    ) : null}
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="todo-list-edit"
                        onClick={onEditList}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="todo-list-delete"
                        onClick={onDeleteList}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            </CardHeader>

            <CardContent className="space-y-3">
                {sortedItems.length > 0 ? (
                    <ul className="space-y-2">
                        {sortedItems.map((item) => (
                            <li
                                key={item.id}
                                data-test="todo-item-row"
                                className="flex items-start gap-3 rounded-lg border p-3"
                            >
                                <Checkbox
                                    checked={item.is_completed}
                                    onCheckedChange={() => toggleItem(item)}
                                    data-test="todo-item-toggle"
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
                                    <p className="text-muted-foreground mt-0.5 flex flex-wrap gap-x-2 text-xs">
                                        <span className="capitalize">
                                            {item.priority}
                                        </span>
                                        {item.due_at ? (
                                            <span>
                                                Due{' '}
                                                {item.due_at.slice(0, 10)}
                                            </span>
                                        ) : null}
                                        {item.estimated_minutes ? (
                                            <span>
                                                {item.estimated_minutes} min
                                            </span>
                                        ) : null}
                                        {item.assignee ? (
                                            <span>{item.assignee.name}</span>
                                        ) : null}
                                    </p>
                                    {item.task && teamSlug ? (
                                        <Link
                                            href={showTask.url([
                                                teamSlug,
                                                item.task.project_id,
                                                item.task.id,
                                            ])}
                                            data-test="todo-item-task-link"
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
                                            todoListId={list.id}
                                            item={item}
                                            taskTypes={taskTypes}
                                            projects={projects}
                                            onPromoted={onChanged}
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                data-test="todo-item-promote"
                                            >
                                                <ArrowUpRight className="h-3.5 w-3.5" />
                                            </Button>
                                        </PromoteTodoItemModal>
                                    ) : null}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="todo-item-edit"
                                        onClick={() => setEditingItem(item)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="todo-item-delete"
                                        onClick={() => deleteItem(item)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground py-4 text-center text-sm">
                        No items yet.
                    </p>
                )}

                <TodoItemFormModal
                    todoListId={list.id}
                    teamMembers={teamMembers}
                    onSaved={onChanged}
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="todo-item-add"
                    >
                        <Plus className="h-4 w-4" /> Add item
                    </Button>
                </TodoItemFormModal>
            </CardContent>

            <TodoItemFormModal
                open={editingItem !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingItem(null);
                    }
                }}
                todoListId={list.id}
                item={editingItem}
                teamMembers={teamMembers}
                onSaved={onChanged}
            />
        </Card>
    );
}

import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/todo-lists/items';
import type { Priority, TeamMemberOption, TodoItem } from '@/types';

export type TodoItemFormData = {
    title: string;
    notes: string;
    priority: Priority;
    due_at: string;
    estimated_minutes: string;
    assigned_to: string;
};

export type TodoItemSavedResponse = {
    item: TodoItem;
    message: string;
};

type Props = {
    todoListId: number;
    item?: TodoItem | null;
    teamMembers: TeamMemberOption[];
    onSaved?: (item: TodoItem, message: string) => void;
    onCancel?: () => void;
};

export default function TodoItemForm({
    todoListId,
    item = null,
    teamMembers,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<TodoItemFormData, TodoItemSavedResponse>(
        () =>
            item
                ? update.put([teamSlug ?? '', todoListId, item.id])
                : store([teamSlug ?? '', todoListId]),
        {
            title: item?.title ?? '',
            notes: item?.notes ?? '',
            priority: item?.priority ?? 'medium',
            due_at: item?.due_at?.slice(0, 10) ?? '',
            estimated_minutes: item?.estimated_minutes
                ? String(item.estimated_minutes)
                : '',
            assigned_to: item?.assignee ? String(item.assignee.id) : 'none',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.item, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="todo-item-title">Title</Label>
                <Input
                    id="todo-item-title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    placeholder="Buy milk"
                    required
                    data-test="todo-item-title"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="todo-item-notes">Notes</Label>
                <Textarea
                    id="todo-item-notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    data-test="todo-item-notes"
                />
                <InputError message={form.errors.notes} />
            </div>

            <div className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="todo-item-priority">Priority</Label>
                    <Select
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value as Priority)
                        }
                    >
                        <SelectTrigger
                            id="todo-item-priority"
                            data-test="todo-item-priority"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.priority} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="todo-item-assignee">Assignee</Label>
                    <Select
                        value={form.data.assigned_to}
                        onValueChange={(value) =>
                            form.setData('assigned_to', value)
                        }
                    >
                        <SelectTrigger
                            id="todo-item-assignee"
                            data-test="todo-item-assignee"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Unassigned</SelectItem>
                            {teamMembers.map((member) => (
                                <SelectItem
                                    key={member.id}
                                    value={String(member.id)}
                                >
                                    {member.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.assigned_to} />
                </div>
            </div>

            <div className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="todo-item-due-at">Due</Label>
                    <Input
                        id="todo-item-due-at"
                        type="date"
                        value={form.data.due_at}
                        onChange={(event) =>
                            form.setData('due_at', event.target.value)
                        }
                        data-test="todo-item-due-at"
                    />
                    <InputError message={form.errors.due_at} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="todo-item-estimated-minutes">
                        Estimate (minutes)
                    </Label>
                    <Input
                        id="todo-item-estimated-minutes"
                        type="number"
                        min="0"
                        step="5"
                        value={form.data.estimated_minutes}
                        onChange={(event) =>
                            form.setData(
                                'estimated_minutes',
                                event.target.value,
                            )
                        }
                        data-test="todo-item-estimated-minutes"
                    />
                    <InputError message={form.errors.estimated_minutes} />
                </div>
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="todo-item-submit"
                >
                    {item ? 'Save changes' : 'Add item'}
                </Button>
            </div>
        </form>
    );
}

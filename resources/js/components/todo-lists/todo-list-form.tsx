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
import { store, update } from '@/routes/todo-lists';
import type {
    TodoList,
    TodoListStatus,
    TodoListStatusOption,
    TodoListType,
    TodoListTypeOption,
} from '@/types';

export type TodoListFormData = {
    name: string;
    description: string;
    type: TodoListType;
    status: TodoListStatus;
    scheduled_for: string;
};

export type TodoListSavedResponse = {
    list: TodoList;
    message: string;
};

type Props = {
    list?: TodoList | null;
    typeOptions: TodoListTypeOption[];
    statusOptions: TodoListStatusOption[];
    onSaved?: (list: TodoList, message: string) => void;
    onCancel?: () => void;
};

export default function TodoListForm({
    list = null,
    typeOptions,
    statusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<TodoListFormData, TodoListSavedResponse>(
        () =>
            list
                ? update.put([teamSlug ?? '', list.id])
                : store(teamSlug ?? ''),
        {
            name: list?.name ?? '',
            description: list?.description ?? '',
            type: list?.type ?? 'custom',
            status: list?.status ?? 'open',
            scheduled_for: list?.scheduled_for ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.list, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="todo-list-name">Name</Label>
                <Input
                    id="todo-list-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Groceries"
                    required
                    data-test="todo-list-name"
                />
                <InputError message={form.errors.name} />
            </div>

            {!list ? (
                <div className="grid gap-2">
                    <Label htmlFor="todo-list-type">Type</Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(value) =>
                            form.setData('type', value as TodoListType)
                        }
                    >
                        <SelectTrigger
                            id="todo-list-type"
                            data-test="todo-list-type"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {typeOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.type} />
                </div>
            ) : null}

            {form.data.type === 'daily' && !list ? (
                <div className="grid gap-2">
                    <Label htmlFor="todo-list-scheduled-for">Date</Label>
                    <Input
                        id="todo-list-scheduled-for"
                        type="date"
                        value={form.data.scheduled_for}
                        onChange={(event) =>
                            form.setData('scheduled_for', event.target.value)
                        }
                        required
                        data-test="todo-list-scheduled-for"
                    />
                    <InputError message={form.errors.scheduled_for} />
                </div>
            ) : null}

            <div className="grid gap-2">
                <Label htmlFor="todo-list-description">Description</Label>
                <Textarea
                    id="todo-list-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="todo-list-description"
                />
                <InputError message={form.errors.description} />
            </div>

            {list ? (
                <div className="grid gap-2">
                    <Label htmlFor="todo-list-status">Status</Label>
                    <Select
                        value={form.data.status}
                        onValueChange={(value) =>
                            form.setData('status', value as TodoListStatus)
                        }
                    >
                        <SelectTrigger
                            id="todo-list-status"
                            data-test="todo-list-status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.status} />
                </div>
            ) : null}

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="todo-list-submit"
                >
                    {list ? 'Save changes' : 'Create list'}
                </Button>
            </div>
        </form>
    );
}

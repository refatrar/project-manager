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
import { store, update } from '@/routes/setup/task-types';
import type { ScopeStatus, ScopeStatusOption, TaskType } from '@/types';

export type TaskTypeFormData = {
    name: string;
    description: string;
    status: ScopeStatus;
};

export type TaskTypeSavedResponse = {
    taskType: TaskType;
    message: string;
};

type Props = {
    taskType?: TaskType | null;
    statusOptions?: ScopeStatusOption[];
    onSaved?: (taskType: TaskType, message: string) => void;
    onCancel?: () => void;
};

const defaultStatusOptions: ScopeStatusOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function TaskTypeForm({
    taskType = null,
    statusOptions = defaultStatusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<TaskTypeFormData, TaskTypeSavedResponse>(
        () =>
            taskType
                ? update.put([teamSlug ?? '', taskType.id])
                : store(teamSlug ?? ''),
        {
            name: taskType?.name ?? '',
            description: taskType?.description ?? '',
            status: taskType?.status ?? 'active',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.taskType, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="task-type-name">Name</Label>
                <Input
                    id="task-type-name"
                    name="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Feature"
                    required
                    data-test="task-type-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="task-type-description">Description</Label>
                <Textarea
                    id="task-type-description"
                    name="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    placeholder="Optional details about this task type"
                    data-test="task-type-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="task-type-status">Status</Label>
                <Select
                    name="status"
                    value={form.data.status}
                    onValueChange={(value) =>
                        form.setData('status', value as ScopeStatus)
                    }
                >
                    <SelectTrigger
                        id="task-type-status"
                        className="w-full"
                        data-test="task-type-status"
                    >
                        <SelectValue placeholder="Select a status" />
                    </SelectTrigger>
                    <SelectContent>
                        {statusOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.status} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="task-type-submit"
                >
                    {taskType ? 'Save changes' : 'Create task type'}
                </Button>
            </div>
        </form>
    );
}

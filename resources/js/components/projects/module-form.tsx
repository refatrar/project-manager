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
import { store, update } from '@/routes/projects/modules';
import type {
    Priority,
    PriorityOption,
    ProjectModule,
    ProjectModuleStatus,
    ProjectModuleStatusOption,
} from '@/types';

export type ModuleFormData = {
    parent_id: string;
    name: string;
    description: string;
    status: ProjectModuleStatus;
    priority: Priority;
    start_date: string;
    end_date: string;
    estimated_hours: string;
};

export type ModuleSavedResponse = {
    module: ProjectModule;
    message: string;
};

type Props = {
    projectId: number;
    module?: ProjectModule | null;
    defaultParentId?: number | null;
    availableParents: ProjectModule[];
    statusOptions: ProjectModuleStatusOption[];
    priorityOptions: PriorityOption[];
    onSaved?: (module: ProjectModule, message: string) => void;
    onCancel?: () => void;
};

export default function ModuleForm({
    projectId,
    module = null,
    defaultParentId = null,
    availableParents,
    statusOptions,
    priorityOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

    const form = useHttp<ModuleFormData, ModuleSavedResponse>(
        () =>
            module
                ? update.put([teamSlug ?? '', projectId, module.id])
                : store([teamSlug ?? '', projectId]),
        {
            parent_id: String(module?.parent_id ?? defaultParentId ?? 'none'),
            name: module?.name ?? '',
            description: module?.description ?? '',
            status: module?.status ?? 'planning',
            priority: module?.priority ?? 'medium',
            start_date: module?.start_date ?? '',
            end_date: module?.end_date ?? '',
            estimated_hours: module?.estimated_hours ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.module, response.message);
            },
        });
    };

    const parentChoices = availableParents.filter(
        (candidate) => candidate.id !== module?.id,
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="module-name">Name</Label>
                <Input
                    id="module-name"
                    name="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Authentication"
                    required
                    data-test="module-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="module-parent">Parent module</Label>
                <Select
                    name="parent_id"
                    value={form.data.parent_id}
                    onValueChange={(value) => form.setData('parent_id', value)}
                >
                    <SelectTrigger id="module-parent" data-test="module-parent">
                        <SelectValue placeholder="No parent (top level)" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No parent (top level)</SelectItem>
                        {parentChoices.map((candidate) => (
                            <SelectItem
                                key={candidate.id}
                                value={String(candidate.id)}
                            >
                                {candidate.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.parent_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="module-description">Description</Label>
                <Textarea
                    id="module-description"
                    name="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="module-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="module-status">Status</Label>
                    <Select
                        name="status"
                        value={form.data.status}
                        onValueChange={(value) =>
                            form.setData('status', value as ProjectModuleStatus)
                        }
                    >
                        <SelectTrigger id="module-status" data-test="module-status">
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

                <div className="grid gap-2">
                    <Label htmlFor="module-priority">Priority</Label>
                    <Select
                        name="priority"
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value as Priority)
                        }
                    >
                        <SelectTrigger id="module-priority" data-test="module-priority">
                            <SelectValue placeholder="Select a priority" />
                        </SelectTrigger>
                        <SelectContent>
                            {priorityOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.priority} />
                </div>
            </div>

            <div className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="module-start-date">Start date</Label>
                    <Input
                        id="module-start-date"
                        type="date"
                        value={form.data.start_date}
                        onChange={(event) =>
                            form.setData('start_date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="module-end-date">End date</Label>
                    <Input
                        id="module-end-date"
                        type="date"
                        value={form.data.end_date}
                        onChange={(event) =>
                            form.setData('end_date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.end_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="module-estimated-hours">Est. hours</Label>
                    <Input
                        id="module-estimated-hours"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.estimated_hours}
                        onChange={(event) =>
                            form.setData('estimated_hours', event.target.value)
                        }
                    />
                    <InputError message={form.errors.estimated_hours} />
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
                    data-test="module-submit"
                >
                    {module ? 'Save changes' : 'Create module'}
                </Button>
            </div>
        </form>
    );
}

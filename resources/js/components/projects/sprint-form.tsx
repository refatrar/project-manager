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
import { store, update } from '@/routes/projects/sprints';
import type { Sprint, SprintStatus, SprintStatusOption } from '@/types';

export type SprintFormData = {
    name: string;
    goal: string;
    status: SprintStatus;
    starts_on: string;
    ends_on: string;
    capacity_hours: string;
    committed_hours: string;
};

export type SprintSavedResponse = {
    sprint: Sprint;
    message: string;
};

type Props = {
    projectId: number;
    sprint?: Sprint | null;
    statusOptions: SprintStatusOption[];
    onSaved?: (sprint: Sprint, message: string) => void;
    onCancel?: () => void;
};

export default function SprintForm({
    projectId,
    sprint = null,
    statusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

    const form = useHttp<SprintFormData, SprintSavedResponse>(
        () =>
            sprint
                ? update.put([teamSlug ?? '', projectId, sprint.id])
                : store([teamSlug ?? '', projectId]),
        {
            name: sprint?.name ?? '',
            goal: sprint?.goal ?? '',
            status: sprint?.status ?? 'planned',
            starts_on: sprint?.starts_on ?? '',
            ends_on: sprint?.ends_on ?? '',
            capacity_hours: sprint?.capacity_hours ?? '',
            committed_hours: sprint?.committed_hours ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.sprint, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="sprint-name">Name</Label>
                <Input
                    id="sprint-name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Sprint 1"
                    required
                    data-test="sprint-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sprint-goal">Goal</Label>
                <Textarea
                    id="sprint-goal"
                    value={form.data.goal}
                    onChange={(event) => form.setData('goal', event.target.value)}
                    data-test="sprint-goal"
                />
                <InputError message={form.errors.goal} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sprint-status">Status</Label>
                <Select
                    value={form.data.status}
                    onValueChange={(value) =>
                        form.setData('status', value as SprintStatus)
                    }
                >
                    <SelectTrigger id="sprint-status" data-test="sprint-status">
                        <SelectValue />
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

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="sprint-starts-on">Starts on</Label>
                    <Input
                        id="sprint-starts-on"
                        type="date"
                        value={form.data.starts_on}
                        onChange={(event) =>
                            form.setData('starts_on', event.target.value)
                        }
                        required
                        data-test="sprint-starts-on"
                    />
                    <InputError message={form.errors.starts_on} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sprint-ends-on">Ends on</Label>
                    <Input
                        id="sprint-ends-on"
                        type="date"
                        value={form.data.ends_on}
                        onChange={(event) =>
                            form.setData('ends_on', event.target.value)
                        }
                        required
                        data-test="sprint-ends-on"
                    />
                    <InputError message={form.errors.ends_on} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="sprint-capacity">Capacity hours</Label>
                    <Input
                        id="sprint-capacity"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.capacity_hours}
                        onChange={(event) =>
                            form.setData('capacity_hours', event.target.value)
                        }
                        data-test="sprint-capacity"
                    />
                    <InputError message={form.errors.capacity_hours} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sprint-committed">Committed hours</Label>
                    <Input
                        id="sprint-committed"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.committed_hours}
                        onChange={(event) =>
                            form.setData('committed_hours', event.target.value)
                        }
                        data-test="sprint-committed"
                    />
                    <InputError message={form.errors.committed_hours} />
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
                    data-test="sprint-submit"
                >
                    {sprint ? 'Save changes' : 'Create sprint'}
                </Button>
            </div>
        </form>
    );
}

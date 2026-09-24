import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { store, update } from '@/routes/time-logs';
import type {
    ProjectOption,
    TimeLog,
    TimeLogActivityType,
    TimeLogActivityTypeOption,
} from '@/types';

export type TimeLogFormData = {
    project_id: string;
    activity_type: TimeLogActivityType;
    description: string;
    is_billable: boolean;
    started_at: string;
    ended_at: string;
};

export type TimeLogSavedResponse = {
    message: string;
};

type Props = {
    log?: TimeLog | null;
    projects: ProjectOption[];
    activityTypeOptions: TimeLogActivityTypeOption[];
    onSaved?: (message: string) => void;
    onCancel?: () => void;
};

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    // Keeps seconds even though the picker only shows minutes: an entry
    // that ran for under a minute has `started_at`/`ended_at` a few
    // seconds apart, which would otherwise round to the same displayed
    // minute and fail the "ended after started" check on an unrelated
    // re-save.
    const date = new Date(value);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

export default function TimeLogForm({ log = null, projects, activityTypeOptions, onSaved, onCancel }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<TimeLogFormData, TimeLogSavedResponse>(
        () => (log ? update.put([teamSlug ?? '', log.id]) : store(teamSlug ?? '')),
        {
            project_id: log?.project_id ? String(log.project_id) : '',
            activity_type: log?.activity_type ?? 'development',
            description: log?.description ?? '',
            is_billable: log?.is_billable ?? true,
            started_at: toLocalInput(log?.started_at ?? null),
            ended_at: toLocalInput(log?.ended_at ?? null),
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => onSaved?.(response.message),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid w-full min-w-0 grid-cols-2 gap-4 *:min-w-0">
                <div className="grid gap-2">
                    <Label htmlFor="time-log-project">Project</Label>
                    <Select
                        value={form.data.project_id}
                        onValueChange={(value) => form.setData('project_id', value)}
                    >
                        <SelectTrigger id="time-log-project" data-test="time-log-project">
                            <SelectValue placeholder="No project" />
                        </SelectTrigger>
                        <SelectContent>
                            {projects.map((project) => (
                                <SelectItem key={project.id} value={String(project.id)}>
                                    {project.code} · {project.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="time-log-activity">Activity</Label>
                    <Select
                        value={form.data.activity_type}
                        onValueChange={(value) => form.setData('activity_type', value as TimeLogActivityType)}
                    >
                        <SelectTrigger id="time-log-activity" data-test="time-log-activity">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {activityTypeOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.activity_type} />
                </div>
            </div>

            <div className="grid w-full min-w-0 grid-cols-2 gap-4 *:min-w-0">
                <div className="grid gap-2">
                    <Label htmlFor="time-log-started-at">Started at</Label>
                    <Input
                        id="time-log-started-at"
                        type="datetime-local"
                        value={form.data.started_at}
                        onChange={(event) => form.setData('started_at', event.target.value)}
                        data-test="time-log-started-at"
                    />
                    <InputError message={form.errors.started_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="time-log-ended-at">Ended at</Label>
                    <Input
                        id="time-log-ended-at"
                        type="datetime-local"
                        value={form.data.ended_at}
                        onChange={(event) => form.setData('ended_at', event.target.value)}
                        data-test="time-log-ended-at"
                    />
                    <InputError message={form.errors.ended_at} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="time-log-description">Description</Label>
                <Textarea
                    id="time-log-description"
                    rows={2}
                    value={form.data.description}
                    onChange={(event) => form.setData('description', event.target.value)}
                    data-test="time-log-description"
                />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="time-log-billable"
                    checked={form.data.is_billable}
                    onCheckedChange={(checked) => form.setData('is_billable', checked === true)}
                />
                <Label htmlFor="time-log-billable">Billable</Label>
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
                    data-test="time-log-submit"
                >
                    {log ? 'Save changes' : 'Log time'}
                </Button>
            </div>
        </form>
    );
}

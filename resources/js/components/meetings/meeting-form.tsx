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
import { store, update } from '@/routes/meetings';
import type {
    Meeting,
    MeetingDetail,
    MeetingType,
    MeetingTypeOption,
    ProjectOption,
} from '@/types';

export type MeetingFormData = {
    project_id: string;
    title: string;
    type: MeetingType;
    agenda: string;
    location: string;
    meeting_url: string;
    scheduled_start: string;
    scheduled_end: string;
};

export type MeetingSavedResponse = {
    meeting: MeetingDetail;
    message: string;
};

type Props = {
    meeting?: Meeting | MeetingDetail | null;
    typeOptions: MeetingTypeOption[];
    projects: ProjectOption[];
    onSaved?: (meeting: MeetingDetail, message: string) => void;
    onCancel?: () => void;
};

function isDetail(
    meeting: Meeting | MeetingDetail | null | undefined,
): meeting is MeetingDetail {
    return meeting !== null && meeting !== undefined && 'agenda' in meeting;
}

export default function MeetingForm({
    meeting = null,
    typeOptions,
    projects,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const detail = isDetail(meeting) ? meeting : null;

    const form = useHttp<MeetingFormData, MeetingSavedResponse>(
        () =>
            meeting
                ? update.put([teamSlug ?? '', meeting.id])
                : store(teamSlug ?? ''),
        {
            project_id: meeting?.project ? String(meeting.project.id) : 'none',
            title: meeting?.title ?? '',
            type: meeting?.type ?? 'general',
            agenda: detail?.agenda ?? '',
            location: detail?.location ?? '',
            meeting_url: detail?.meeting_url ?? '',
            scheduled_start: meeting?.scheduled_start?.slice(0, 16) ?? '',
            scheduled_end: meeting?.scheduled_end?.slice(0, 16) ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.meeting, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="meeting-title">Title</Label>
                <Input
                    id="meeting-title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    placeholder="Sprint planning"
                    required
                    data-test="meeting-title"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="meeting-type">Type</Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(value) =>
                            form.setData('type', value as MeetingType)
                        }
                    >
                        <SelectTrigger id="meeting-type" data-test="meeting-type">
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

                <div className="grid gap-2">
                    <Label htmlFor="meeting-project">Project</Label>
                    <Select
                        value={form.data.project_id}
                        onValueChange={(value) =>
                            form.setData('project_id', value)
                        }
                    >
                        <SelectTrigger
                            id="meeting-project"
                            data-test="meeting-project"
                        >
                            <SelectValue placeholder="No project" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                No project (team-wide)
                            </SelectItem>
                            {projects.map((project) => (
                                <SelectItem
                                    key={project.id}
                                    value={String(project.id)}
                                >
                                    {project.code} · {project.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.project_id} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="meeting-scheduled-start">Starts</Label>
                    <Input
                        id="meeting-scheduled-start"
                        type="datetime-local"
                        value={form.data.scheduled_start}
                        onChange={(event) =>
                            form.setData(
                                'scheduled_start',
                                event.target.value,
                            )
                        }
                        required
                        data-test="meeting-scheduled-start"
                    />
                    <InputError message={form.errors.scheduled_start} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="meeting-scheduled-end">Ends</Label>
                    <Input
                        id="meeting-scheduled-end"
                        type="datetime-local"
                        value={form.data.scheduled_end}
                        onChange={(event) =>
                            form.setData('scheduled_end', event.target.value)
                        }
                        required
                        data-test="meeting-scheduled-end"
                    />
                    <InputError message={form.errors.scheduled_end} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="meeting-location">Location</Label>
                    <Input
                        id="meeting-location"
                        value={form.data.location}
                        onChange={(event) =>
                            form.setData('location', event.target.value)
                        }
                        placeholder="Conference room B"
                        data-test="meeting-location"
                    />
                    <InputError message={form.errors.location} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="meeting-url">Meeting URL</Label>
                    <Input
                        id="meeting-url"
                        type="url"
                        value={form.data.meeting_url}
                        onChange={(event) =>
                            form.setData('meeting_url', event.target.value)
                        }
                        placeholder="https://..."
                        data-test="meeting-url"
                    />
                    <InputError message={form.errors.meeting_url} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="meeting-agenda">Agenda notes</Label>
                <Textarea
                    id="meeting-agenda"
                    value={form.data.agenda}
                    onChange={(event) =>
                        form.setData('agenda', event.target.value)
                    }
                    placeholder="Freeform notes to share before the meeting"
                    data-test="meeting-agenda"
                />
                <InputError message={form.errors.agenda} />
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
                    data-test="meeting-submit"
                >
                    {meeting ? 'Save changes' : 'Schedule meeting'}
                </Button>
            </div>
        </form>
    );
}

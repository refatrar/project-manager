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
import { store, update } from '@/routes/meetings/agenda-items';
import type {
    MeetingAgendaItem,
    TaskReference,
    TeamMemberOption,
} from '@/types';

export type AgendaItemFormData = {
    title: string;
    description: string;
    notes: string;
    duration_minutes: string;
    task_id: string;
    presenter_id: string;
};

export type AgendaItemSavedResponse = {
    agendaItem: MeetingAgendaItem;
    message: string;
};

type Props = {
    meetingId: number;
    item?: MeetingAgendaItem | null;
    projectTasks: TaskReference[];
    teamMembers: TeamMemberOption[];
    onSaved?: (item: MeetingAgendaItem, message: string) => void;
    onCancel?: () => void;
};

export default function AgendaItemForm({
    meetingId,
    item = null,
    projectTasks,
    teamMembers,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<AgendaItemFormData, AgendaItemSavedResponse>(
        () =>
            item
                ? update.put([teamSlug ?? '', meetingId, item.id])
                : store([teamSlug ?? '', meetingId]),
        {
            title: item?.title ?? '',
            description: item?.description ?? '',
            notes: item?.notes ?? '',
            duration_minutes: item?.duration_minutes
                ? String(item.duration_minutes)
                : '',
            task_id: item?.task ? String(item.task.id) : 'none',
            presenter_id: item?.presenter ? String(item.presenter.id) : 'none',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.agendaItem, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="agenda-item-title">Title</Label>
                <Input
                    id="agenda-item-title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    placeholder="Review open blockers"
                    required
                    data-test="agenda-item-title"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="agenda-item-description">Description</Label>
                <Textarea
                    id="agenda-item-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="agenda-item-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="agenda-item-presenter">Presenter</Label>
                    <Select
                        value={form.data.presenter_id}
                        onValueChange={(value) =>
                            form.setData('presenter_id', value)
                        }
                    >
                        <SelectTrigger
                            id="agenda-item-presenter"
                            data-test="agenda-item-presenter"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No presenter</SelectItem>
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
                    <InputError message={form.errors.presenter_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="agenda-item-duration">
                        Duration (minutes)
                    </Label>
                    <Input
                        id="agenda-item-duration"
                        type="number"
                        min="0"
                        step="5"
                        value={form.data.duration_minutes}
                        onChange={(event) =>
                            form.setData(
                                'duration_minutes',
                                event.target.value,
                            )
                        }
                        data-test="agenda-item-duration"
                    />
                    <InputError message={form.errors.duration_minutes} />
                </div>
            </div>

            {projectTasks.length > 0 ? (
                <div className="grid gap-2">
                    <Label htmlFor="agenda-item-task">Linked task</Label>
                    <Select
                        value={form.data.task_id}
                        onValueChange={(value) =>
                            form.setData('task_id', value)
                        }
                    >
                        <SelectTrigger
                            id="agenda-item-task"
                            data-test="agenda-item-task"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No linked task</SelectItem>
                            {projectTasks.map((task) => (
                                <SelectItem
                                    key={task.id}
                                    value={String(task.id)}
                                >
                                    {task.reference} {task.title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.task_id} />
                </div>
            ) : null}

            <div className="grid gap-2">
                <Label htmlFor="agenda-item-notes">Notes</Label>
                <Textarea
                    id="agenda-item-notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    data-test="agenda-item-notes"
                />
                <InputError message={form.errors.notes} />
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
                    data-test="agenda-item-submit"
                >
                    {item ? 'Save changes' : 'Add item'}
                </Button>
            </div>
        </form>
    );
}

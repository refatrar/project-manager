import { useHttp, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { publish, update } from '@/routes/meetings/minutes';
import type { MeetingDetail } from '@/types';

type MinutesFormData = {
    minutes: string;
    decisions: string;
};

type SavedResponse = {
    meeting: MeetingDetail;
    message: string;
};

type Props = {
    meeting: MeetingDetail;
    /** Whether the user may record/publish minutes (server `can.recordMinutes`). */
    canEdit: boolean;
    onChanged: () => void;
};

export default function MinutesEditor({ meeting, canEdit, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<MinutesFormData, SavedResponse>(
        () => update([teamSlug ?? '', meeting.id]),
        {
            minutes: meeting.minutes ?? '',
            decisions: meeting.decisions ?? '',
        },
    );
    const publishForm = useHttp<Record<string, never>, SavedResponse>({});

    // The text as last saved on the server. Publishing sends nothing, so
    // unsaved edits must be saved first or they would be left out of the
    // minutes that attendees are emailed.
    const [saved, setSaved] = useState<MinutesFormData>({
        minutes: meeting.minutes ?? '',
        decisions: meeting.decisions ?? '',
    });
    const isDirty =
        form.data.minutes !== saved.minutes ||
        form.data.decisions !== saved.decisions;

    const save = (onSaved?: () => void) => {
        const failed = () => {
            toast.error(
                onSaved
                    ? 'The minutes could not be saved, so they were not published.'
                    : 'The minutes could not be saved.',
            );
        };

        void form.submit({
            onSuccess: (response) => {
                setSaved({
                    minutes: response.meeting.minutes ?? '',
                    decisions: response.meeting.decisions ?? '',
                });

                if (onSaved) {
                    onSaved();

                    return;
                }

                toast.success(response.message);
                onChanged();
            },
            onError: failed,
            onHttpException: failed,
            onNetworkError: failed,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        save();
    };

    const sendPublish = () => {
        if (!teamSlug) {
            return;
        }

        void publishForm.patch(publish.url([teamSlug, meeting.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    const publishMinutes = () => {
        if (isDirty) {
            save(sendPublish);

            return;
        }

        sendPublish();
    };

    const busy = form.processing || publishForm.processing;

    const publishedBadge = meeting.minutes_published_at ? (
        <Badge variant="outline" data-test="minutes-published-badge">
            Published {new Date(meeting.minutes_published_at).toLocaleString()}
            {meeting.recorder ? ` by ${meeting.recorder.name}` : ''}
        </Badge>
    ) : null;

    if (!canEdit) {
        return (
            <div className="space-y-6" data-test="minutes-read-only">
                {publishedBadge}

                <div className="grid gap-2">
                    <p className="text-sm font-medium">Minutes</p>
                    <p className="text-sm whitespace-pre-wrap">
                        {meeting.minutes || (
                            <span className="text-muted-foreground">
                                No minutes recorded yet.
                            </span>
                        )}
                    </p>
                </div>

                <div className="grid gap-2">
                    <p className="text-sm font-medium">Decisions</p>
                    <p className="text-sm whitespace-pre-wrap">
                        {meeting.decisions || (
                            <span className="text-muted-foreground">
                                No decisions recorded yet.
                            </span>
                        )}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            {publishedBadge}

            <div className="grid gap-2">
                <Label htmlFor="meeting-minutes">Minutes</Label>
                <Textarea
                    id="meeting-minutes"
                    rows={6}
                    value={form.data.minutes}
                    onChange={(event) =>
                        form.setData('minutes', event.target.value)
                    }
                    placeholder="What was discussed..."
                    data-test="meeting-minutes"
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="meeting-decisions">Decisions</Label>
                <Textarea
                    id="meeting-decisions"
                    rows={3}
                    value={form.data.decisions}
                    onChange={(event) =>
                        form.setData('decisions', event.target.value)
                    }
                    placeholder="What was decided..."
                    data-test="meeting-decisions"
                />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={busy || !teamSlug}
                    data-test="minutes-save"
                >
                    Save minutes
                </Button>

                {!meeting.minutes_published_at ? (
                    <Button
                        type="button"
                        disabled={busy || !teamSlug}
                        onClick={publishMinutes}
                        data-test="minutes-publish"
                    >
                        {isDirty ? 'Save & publish minutes' : 'Publish minutes'}
                    </Button>
                ) : null}

                <p
                    className="text-muted-foreground text-sm"
                    aria-live="polite"
                    data-test="minutes-dirty-hint"
                >
                    {isDirty ? 'Unsaved changes' : ''}
                </p>
            </div>
        </form>
    );
}

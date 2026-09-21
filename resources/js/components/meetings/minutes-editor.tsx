import { useHttp, usePage } from '@inertiajs/react';
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
    onChanged: () => void;
};

export default function MinutesEditor({ meeting, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<MinutesFormData, SavedResponse>(
        () => update([teamSlug ?? '', meeting.id]),
        {
            minutes: meeting.minutes ?? '',
            decisions: meeting.decisions ?? '',
        },
    );
    const publishForm = useHttp<Record<string, never>, SavedResponse>({});

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    const publishMinutes = () => {
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

    return (
        <form onSubmit={submit} className="space-y-6">
            {meeting.minutes_published_at ? (
                <Badge variant="outline" data-test="minutes-published-badge">
                    Published{' '}
                    {new Date(meeting.minutes_published_at).toLocaleString()}
                    {meeting.recorder ? ` by ${meeting.recorder.name}` : ''}
                </Badge>
            ) : null}

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

            <div className="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing || !teamSlug}
                    data-test="minutes-save"
                >
                    Save minutes
                </Button>

                {!meeting.minutes_published_at ? (
                    <Button
                        type="button"
                        disabled={publishForm.processing || !teamSlug}
                        onClick={publishMinutes}
                        data-test="minutes-publish"
                    >
                        Publish minutes
                    </Button>
                ) : null}
            </div>
        </form>
    );
}

import { useHttp, usePage } from '@inertiajs/react';
import { Play, Square } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { start, stop } from '@/routes/meetings/timer';
import type { MeetingTimer as MeetingTimerData } from '@/types';

type TimerResponse = {
    timer: MeetingTimerData;
    message: string;
};

type Props = {
    meetingId: number;
    timer: MeetingTimerData;
    onChanged: () => void;
};

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return hours > 0 ? `${hours}h ${remaining}m` : `${remaining}m`;
}

function formatElapsed(seconds: number): string {
    const hh = Math.floor(seconds / 3600);
    const mm = Math.floor((seconds % 3600) / 60);
    const ss = seconds % 60;

    return [hh, mm, ss].map((part) => String(part).padStart(2, '0')).join(':');
}

export default function MeetingTimer({ meetingId, timer, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, TimerResponse>({});
    const [elapsedSeconds, setElapsedSeconds] = useState(0);

    useEffect(() => {
        if (!timer.running) {
            setElapsedSeconds(0);

            return;
        }

        const startedAt = new Date(timer.running.started_at).getTime();
        const tick = () => setElapsedSeconds(Math.max(0, Math.floor((Date.now() - startedAt) / 1000)));
        tick();

        const interval = window.setInterval(tick, 1000);

        return () => window.clearInterval(interval);
    }, [timer.running]);

    const startTimer = () => {
        if (!teamSlug) {
            return;
        }

        void form.post(start.url([teamSlug, meetingId]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
            onError: () => toast.error('Could not start the timer.'),
        });
    };

    const stopTimer = () => {
        if (!teamSlug) {
            return;
        }

        void form.patch(stop.url([teamSlug, meetingId]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    return (
        <div className="flex flex-wrap items-center gap-4">
            {timer.running ? (
                <>
                    <span
                        className="font-mono text-lg tabular-nums"
                        data-test="meeting-timer-elapsed"
                    >
                        {formatElapsed(elapsedSeconds)}
                    </span>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        disabled={form.processing || !teamSlug}
                        onClick={stopTimer}
                        data-test="meeting-timer-stop"
                    >
                        <Square className="h-4 w-4" /> Stop timer
                    </Button>
                </>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={form.processing || !teamSlug}
                    onClick={startTimer}
                    data-test="meeting-timer-start"
                >
                    <Play className="h-4 w-4" /> Start timer
                </Button>
            )}

            <p className="text-muted-foreground text-sm" data-test="meeting-timer-total">
                {timer.total_minutes > 0
                    ? `${formatMinutes(timer.total_minutes)} logged for this meeting`
                    : 'No time logged yet'}
            </p>
        </div>
    );
}

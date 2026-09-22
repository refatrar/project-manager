import { useHttp, usePage } from '@inertiajs/react';
import { Play, Square } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import { start, stop } from '@/routes/time-logs';
import type { ProjectOption, TimeLog, TimeLogActivityType, TimeLogActivityTypeOption } from '@/types';

type StartedResponse = {
    message: string;
};

type StartFormData = {
    activity_type: TimeLogActivityType;
    project_id: string | null;
    description: string | null;
};

type Props = {
    running: TimeLog | null;
    projects: ProjectOption[];
    activityTypeOptions: TimeLogActivityTypeOption[];
    onChanged: () => void;
};

function formatElapsed(seconds: number): string {
    const hh = Math.floor(seconds / 3600);
    const mm = Math.floor((seconds % 3600) / 60);
    const ss = seconds % 60;

    return [hh, mm, ss].map((part) => String(part).padStart(2, '0')).join(':');
}

export default function TimeLogTimer({ running, projects, activityTypeOptions, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [activityType, setActivityType] = useState<TimeLogActivityType>('development');
    const [projectId, setProjectId] = useState('');
    const [description, setDescription] = useState('');
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const form = useHttp<StartFormData, StartedResponse>({
        activity_type: 'development',
        project_id: null,
        description: null,
    });

    useEffect(() => {
        if (!running) {
            setElapsedSeconds(0);

            return;
        }

        const startedAt = new Date(running.started_at).getTime();
        const tick = () => setElapsedSeconds(Math.max(0, Math.floor((Date.now() - startedAt) / 1000)));
        tick();

        const interval = window.setInterval(tick, 1000);

        return () => window.clearInterval(interval);
    }, [running]);

    const startTimer = () => {
        if (!teamSlug) {
            return;
        }

        form.transform(() => ({
            activity_type: activityType,
            project_id: projectId || null,
            description: description || null,
        }));

        void form.post(start.url(teamSlug), {
            onSuccess: (response) => {
                toast.success(response.message);
                setDescription('');
                onChanged();
            },
            onError: () => toast.error('Could not start the timer.'),
        });
    };

    const stopTimer = () => {
        if (!teamSlug || !running) {
            return;
        }

        void form.patch(stop.url([teamSlug, running.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    if (running) {
        return (
            <div className="flex flex-wrap items-center gap-4">
                <span className="font-mono text-lg tabular-nums" data-test="time-log-timer-elapsed">
                    {formatElapsed(elapsedSeconds)}
                </span>
                <span className="text-muted-foreground text-sm">
                    {running.project ? `${running.project.code} · ` : ''}
                    {running.activity_type.replace('_', ' ')}
                </span>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    disabled={form.processing || !teamSlug}
                    onClick={stopTimer}
                    data-test="time-log-timer-stop"
                >
                    <Square className="h-4 w-4" /> Stop timer
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="grid gap-2">
                <Label htmlFor="timer-project">Project</Label>
                <Select value={projectId} onValueChange={setProjectId}>
                    <SelectTrigger id="timer-project" className="w-48" data-test="timer-project">
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
                <Label htmlFor="timer-activity">Activity</Label>
                <Select value={activityType} onValueChange={(value) => setActivityType(value as TimeLogActivityType)}>
                    <SelectTrigger id="timer-activity" className="w-40" data-test="timer-activity">
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
            </div>

            <div className="grid flex-1 gap-2">
                <Label htmlFor="timer-description">Description</Label>
                <Input
                    id="timer-description"
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    data-test="timer-description"
                />
            </div>

            <Button
                type="button"
                variant="outline"
                disabled={form.processing || !teamSlug}
                onClick={startTimer}
                data-test="time-log-timer-start"
            >
                <Play className="h-4 w-4" /> Start timer
            </Button>
        </div>
    );
}

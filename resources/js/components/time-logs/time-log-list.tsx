import { useHttp, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import TimeLogFormModal from '@/components/time-logs/time-log-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/time-logs';
import type { ProjectOption, TimeLog, TimeLogActivityTypeOption } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    logs: TimeLog[];
    projects: ProjectOption[];
    activityTypeOptions: TimeLogActivityTypeOption[];
    onChanged: () => void;
};

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return hours > 0 ? `${hours}h ${remaining}m` : `${remaining}m`;
}

export default function TimeLogList({ logs, projects, activityTypeOptions, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editingLog, setEditingLog] = useState<TimeLog | null>(null);
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteLog = (log: TimeLog) => {
        if (!teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, log.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    if (logs.length === 0) {
        return <p className="text-muted-foreground py-4 text-center text-sm">No time logged in the last 30 days.</p>;
    }

    return (
        <>
            <ul className="space-y-2">
                {logs.map((log) => (
                    <li
                        key={log.id}
                        data-test="time-log-row"
                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3"
                    >
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium capitalize">
                                    {log.activity_type.replace('_', ' ')}
                                </span>
                                <Badge variant="outline">{formatMinutes(log.duration_minutes)}</Badge>
                                {log.approval_status !== 'pending' ? (
                                    <Badge variant="secondary">{log.approval_status}</Badge>
                                ) : null}
                                {!log.is_billable ? <Badge variant="secondary">Non-billable</Badge> : null}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {log.logged_on}
                                {log.project ? ` · ${log.project.code} ${log.project.name}` : ''}
                                {log.task ? ` · ${log.task.reference}` : ''}
                            </p>
                            {log.description ? (
                                <p className="text-muted-foreground mt-1 text-sm">{log.description}</p>
                            ) : null}
                        </div>

                        {log.approval_status === 'pending' && log.ended_at ? (
                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="time-log-edit"
                                    onClick={() => setEditingLog(log)}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="time-log-delete"
                                    onClick={() => deleteLog(log)}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        ) : null}
                    </li>
                ))}
            </ul>

            <TimeLogFormModal
                open={editingLog !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingLog(null);
                    }
                }}
                log={editingLog}
                projects={projects}
                activityTypeOptions={activityTypeOptions}
                onSaved={onChanged}
            />
        </>
    );
}

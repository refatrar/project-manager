import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import DecideTimeLogModal from '@/components/time-logs/decide-time-log-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as timesheetApprovalsIndex } from '@/routes/timesheet-approvals';
import type { TimeLog } from '@/types';

type Props = {
    entries: TimeLog[];
};

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return hours > 0 ? `${hours}h ${remaining}m` : `${remaining}m`;
}

export default function TimesheetApprovalsIndex({ entries }: Props) {
    const [decision, setDecision] = useState<{ entry: TimeLog; decision: 'approved' | 'rejected' } | null>(null);

    const refresh = () => router.reload({ only: ['entries'] });

    return (
        <>
            <Head title="Timesheet Approvals" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Timesheet Approvals"
                    description="Submitted time logs for the projects you manage, waiting on a decision."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Pending review</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {entries.length > 0 ? (
                            <ul className="space-y-2">
                                {entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        data-test="timesheet-approval-row"
                                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                {entry.user.name}
                                                <span className="text-muted-foreground font-normal">
                                                    {' '}
                                                    · {formatMinutes(entry.duration_minutes)} ·{' '}
                                                    <span className="capitalize">
                                                        {entry.activity_type.replace('_', ' ')}
                                                    </span>
                                                </span>
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {entry.logged_on}
                                                {entry.project ? ` · ${entry.project.code} ${entry.project.name}` : ' · No project'}
                                                {entry.task ? ` · ${entry.task.reference}` : ''}
                                            </p>
                                            {entry.description ? (
                                                <p className="text-muted-foreground mt-1 text-sm">{entry.description}</p>
                                            ) : null}
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                data-test="timesheet-approve"
                                                onClick={() => setDecision({ entry, decision: 'approved' })}
                                            >
                                                <Check className="h-4 w-4" /> Approve
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                data-test="timesheet-reject"
                                                onClick={() => setDecision({ entry, decision: 'rejected' })}
                                            >
                                                <X className="h-4 w-4" /> Reject
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                Nothing waiting on your review.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <DecideTimeLogModal
                entry={decision?.entry ?? null}
                decision={decision?.decision ?? 'approved'}
                open={decision !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDecision(null);
                    }
                }}
                onDecided={refresh}
            />
        </>
    );
}

TimesheetApprovalsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Timesheet Approvals',
            href: props.currentTeam ? timesheetApprovalsIndex(props.currentTeam.slug) : '/',
        },
    ],
});

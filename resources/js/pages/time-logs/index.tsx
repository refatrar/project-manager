import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import TimeLogFormModal from '@/components/time-logs/time-log-form-modal';
import TimeLogList from '@/components/time-logs/time-log-list';
import TimeLogTimer from '@/components/time-logs/time-log-timer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as timeLogsIndex } from '@/routes/time-logs';
import type { ProjectOption, TimeLog, TimeLogActivityTypeOption } from '@/types';

type Props = {
    logs: TimeLog[];
    running: TimeLog | null;
    projects: ProjectOption[];
    activityTypeOptions: TimeLogActivityTypeOption[];
};

export default function TimeLogsIndex({ logs, running, projects, activityTypeOptions }: Props) {
    const refresh = () => router.reload({ only: ['logs', 'running'] });

    return (
        <>
            <Head title="Time Logs" />

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Time Logs"
                        description="Actual effort, separate from planned bookings. A running timer, or a manual entry for time already worked."
                    />

                    <TimeLogFormModal
                        projects={projects}
                        activityTypeOptions={activityTypeOptions}
                        onSaved={refresh}
                    >
                        <Button type="button" data-test="time-log-create-button">
                            <Plus /> Log time
                        </Button>
                    </TimeLogFormModal>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Timer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TimeLogTimer
                            running={running}
                            projects={projects}
                            activityTypeOptions={activityTypeOptions}
                            onChanged={refresh}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Last 30 days</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TimeLogList
                            logs={logs}
                            projects={projects}
                            activityTypeOptions={activityTypeOptions}
                            onChanged={refresh}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TimeLogsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Time Logs',
            href: props.currentTeam ? timeLogsIndex(props.currentTeam.slug) : '/',
        },
    ],
});

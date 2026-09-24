import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import AgendaList from '@/components/meetings/agenda-list';
import AttendeeList from '@/components/meetings/attendee-list';
import MeetingActionItems from '@/components/meetings/meeting-action-items';
import MeetingCancelModal from '@/components/meetings/meeting-cancel-modal';
import MeetingDeleteModal from '@/components/meetings/meeting-delete-modal';
import MeetingFormModal from '@/components/meetings/meeting-form-modal';
import MeetingTimer from '@/components/meetings/meeting-timer';
import MinutesEditor from '@/components/meetings/minutes-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show } from '@/routes/meetings';
import type {
    MeetingAgendaItem,
    MeetingAttendanceStatusOption,
    MeetingAttendee,
    MeetingAttendeeRoleOption,
    MeetingDetail,
    MeetingTimer as MeetingTimerData,
    MeetingTypeOption,
    ProjectOption,
    TaskReference,
    TaskTypeOption,
    TeamMemberOption,
    TodoList,
} from '@/types';
import type { MeetingAbilities } from '@/types/meetings';

type Props = {
    meeting: MeetingDetail;
    attendees: MeetingAttendee[];
    agendaItems: MeetingAgendaItem[];
    actionList: TodoList;
    timer: MeetingTimerData;
    teamMembers: TeamMemberOption[];
    roleOptions: MeetingAttendeeRoleOption[];
    attendanceStatusOptions: MeetingAttendanceStatusOption[];
    typeOptions: MeetingTypeOption[];
    projects: ProjectOption[];
    projectTasks: TaskReference[];
    taskTypes: TaskTypeOption[];
    can: MeetingAbilities;
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    scheduled: 'default',
    in_progress: 'secondary',
    completed: 'outline',
    cancelled: 'destructive',
};

export default function MeetingShow({
    meeting,
    attendees,
    agendaItems,
    actionList,
    timer,
    teamMembers,
    roleOptions,
    attendanceStatusOptions,
    typeOptions,
    projects,
    projectTasks,
    taskTypes,
    can,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editOpen, setEditOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const reload = (only: string[]) => router.reload({ only });

    return (
        <>
            <Head title={meeting.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Heading title={meeting.title} />
                            <Badge
                                variant={
                                    statusVariant[meeting.status] ?? 'default'
                                }
                            >
                                {meeting.status.replace('_', ' ')}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {meeting.type.replace('_', ' ')}
                            {meeting.project
                                ? ` · ${meeting.project.code} ${meeting.project.name}`
                                : ' · Team-wide'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.update ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditOpen(true)}
                                data-test="meeting-edit-button"
                            >
                                <Pencil className="h-4 w-4" /> Edit
                            </Button>
                        ) : null}
                        {can.cancel && meeting.status !== 'cancelled' ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCancelOpen(true)}
                                data-test="meeting-cancel-button"
                            >
                                <XCircle className="h-4 w-4" /> Cancel
                            </Button>
                        ) : null}
                        {can.delete ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteOpen(true)}
                                data-test="meeting-delete-button"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Schedule</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>
                                Starts:{' '}
                                {new Date(
                                    meeting.scheduled_start,
                                ).toLocaleString()}
                            </p>
                            <p>
                                Ends:{' '}
                                {new Date(
                                    meeting.scheduled_end,
                                ).toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Where</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>{meeting.location ?? 'No location set'}</p>
                            {meeting.meeting_url ? (
                                <a
                                    href={meeting.meeting_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    {meeting.meeting_url}
                                </a>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organizer</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {meeting.organizer?.name ?? 'Unassigned'}
                        </CardContent>
                    </Card>
                </div>

                {meeting.agenda ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Agenda notes</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {meeting.agenda}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Attendees</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AttendeeList
                            meetingId={meeting.id}
                            attendees={attendees}
                            teamMembers={teamMembers}
                            roleOptions={roleOptions}
                            attendanceStatusOptions={attendanceStatusOptions}
                            canManage={can.update}
                            onChanged={() => reload(['attendees'])}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Agenda</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AgendaList
                            meetingId={meeting.id}
                            agendaItems={agendaItems}
                            projectTasks={projectTasks}
                            teamMembers={teamMembers}
                            canManage={can.update}
                            onChanged={() => reload(['agendaItems'])}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Minutes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MinutesEditor
                            meeting={meeting}
                            canEdit={can.recordMinutes}
                            onChanged={() => reload(['meeting'])}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Timer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MeetingTimer
                            meetingId={meeting.id}
                            timer={timer}
                            canStart={can.startTimer}
                            onChanged={() => reload(['timer'])}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Action items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MeetingActionItems
                            actionList={actionList}
                            teamMembers={teamMembers}
                            projects={projects}
                            taskTypes={taskTypes}
                            onChanged={() => reload(['actionList'])}
                        />
                    </CardContent>
                </Card>
            </div>

            {can.update ? (
                <MeetingFormModal
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    meeting={meeting}
                    typeOptions={typeOptions}
                    projects={projects}
                    onSaved={() => reload(['meeting', 'can'])}
                />
            ) : null}

            {can.cancel ? (
                <MeetingCancelModal
                    meeting={meeting}
                    open={cancelOpen}
                    onOpenChange={setCancelOpen}
                    onCancelled={() => reload(['meeting'])}
                />
            ) : null}

            {can.delete ? (
                <MeetingDeleteModal
                    meeting={meeting}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                    onDeleted={() => {
                        if (teamSlug) {
                            router.visit(index(teamSlug));
                        }
                    }}
                />
            ) : null}
        </>
    );
}

MeetingShow.layout = (props: {
    meeting: MeetingDetail;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Meetings',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.meeting.title,
            href: '#',
        },
    ],
});

import { useHttp, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import AttendeeInviteModal from '@/components/meetings/attendee-invite-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, update } from '@/routes/meetings/attendees';
import type {
    MeetingAttendanceStatusOption,
    MeetingAttendee,
    MeetingAttendeeRoleOption,
    TeamMemberOption,
} from '@/types';

type UpdatedResponse = {
    attendee: MeetingAttendee;
    message: string;
};

type Props = {
    meetingId: number;
    attendees: MeetingAttendee[];
    teamMembers: TeamMemberOption[];
    roleOptions: MeetingAttendeeRoleOption[];
    attendanceStatusOptions: MeetingAttendanceStatusOption[];
    onChanged: () => void;
};

const attendanceVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    invited: 'outline',
    accepted: 'default',
    tentative: 'secondary',
    declined: 'destructive',
    attended: 'default',
    absent: 'destructive',
};

export default function AttendeeList({
    meetingId,
    attendees,
    teamMembers,
    roleOptions,
    attendanceStatusOptions,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<
        { role: string; attendance_status: string },
        UpdatedResponse
    >({ role: '', attendance_status: '' });

    const updateAttendance = (attendee: MeetingAttendee, status: string) => {
        if (!teamSlug) {
            return;
        }

        form.transform(() => ({ role: attendee.role, attendance_status: status }));

        void form.put(update.url([teamSlug, meetingId, attendee.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that attendee.'),
        });
    };

    const updateRole = (attendee: MeetingAttendee, role: string) => {
        if (!teamSlug) {
            return;
        }

        form.transform(() => ({
            role,
            attendance_status: attendee.attendance_status,
        }));

        void form.put(update.url([teamSlug, meetingId, attendee.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that attendee.'),
        });
    };

    const removeAttendee = (attendee: MeetingAttendee) => {
        if (!teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, meetingId, attendee.id]), {
            onSuccess: () => {
                toast.success('Attendee removed.');
                onChanged();
            },
        });
    };

    return (
        <div className="space-y-3">
            {attendees.length > 0 ? (
                <ul className="space-y-2">
                    {attendees.map((attendee) => (
                        <li
                            key={attendee.id}
                            data-test="attendee-row"
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    {attendee.user?.name ?? attendee.guest_name}
                                    {!attendee.user ? (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            Guest
                                        </Badge>
                                    ) : null}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {attendee.user?.email ??
                                        attendee.guest_email}
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <Select
                                    value={attendee.role}
                                    onValueChange={(value) =>
                                        updateRole(attendee, value)
                                    }
                                >
                                    <SelectTrigger
                                        className="h-8 w-36 text-xs"
                                        data-test="attendee-role-select"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roleOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={attendee.attendance_status}
                                    onValueChange={(value) =>
                                        updateAttendance(attendee, value)
                                    }
                                >
                                    <SelectTrigger
                                        className="h-8 w-36 text-xs"
                                        data-test="attendee-status-select"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {attendanceStatusOptions.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>

                                <Badge
                                    variant={
                                        attendanceVariant[
                                            attendee.attendance_status
                                        ] ?? 'outline'
                                    }
                                >
                                    {attendee.attendance_status}
                                </Badge>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="attendee-remove"
                                    onClick={() => removeAttendee(attendee)}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-muted-foreground py-4 text-center text-sm">
                    No attendees yet.
                </p>
            )}

            <AttendeeInviteModal
                meetingId={meetingId}
                teamMembers={teamMembers}
                roleOptions={roleOptions}
                onInvited={onChanged}
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="attendee-invite-button"
                >
                    <Plus className="h-4 w-4" /> Invite attendee
                </Button>
            </AttendeeInviteModal>
        </div>
    );
}

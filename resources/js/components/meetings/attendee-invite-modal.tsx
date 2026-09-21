import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent, PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/meetings/attendees';
import type {
    MeetingAttendee,
    MeetingAttendeeRole,
    MeetingAttendeeRoleOption,
    TeamMemberOption,
} from '@/types';

type InviteFormData = {
    invite_type: 'member' | 'guest';
    user_id: string;
    guest_name: string;
    guest_email: string;
    role: MeetingAttendeeRole;
};

type InvitedResponse = {
    attendee: MeetingAttendee;
    message: string;
};

type Props = PropsWithChildren<{
    meetingId: number;
    teamMembers: TeamMemberOption[];
    roleOptions: MeetingAttendeeRoleOption[];
    onInvited?: () => void;
}>;

export default function AttendeeInviteModal({
    children,
    meetingId,
    teamMembers,
    roleOptions,
    onInvited,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [open, setOpen] = useState(false);
    const form = useHttp<InviteFormData, InvitedResponse>(
        () => store([teamSlug ?? '', meetingId]),
        {
            invite_type: 'member',
            user_id: '',
            guest_name: '',
            guest_email: '',
            role: 'participant',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                toast.success(response.message);
                setOpen(false);
                form.reset();
                onInvited?.();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite attendee</DialogTitle>
                    <DialogDescription>
                        Invite a team member, or an external guest by name
                        and email.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="attendee-invite-type">Invite</Label>
                        <Select
                            value={form.data.invite_type}
                            onValueChange={(value) =>
                                form.setData(
                                    'invite_type',
                                    value as 'member' | 'guest',
                                )
                            }
                        >
                            <SelectTrigger
                                id="attendee-invite-type"
                                data-test="attendee-invite-type"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="member">
                                    Team member
                                </SelectItem>
                                <SelectItem value="guest">
                                    External guest
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {form.data.invite_type === 'member' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="attendee-user">Member</Label>
                            <Select
                                value={form.data.user_id}
                                onValueChange={(value) =>
                                    form.setData('user_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="attendee-user"
                                    data-test="attendee-user"
                                >
                                    <SelectValue placeholder="Select a member" />
                                </SelectTrigger>
                                <SelectContent>
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
                            <InputError message={form.errors.user_id} />
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="attendee-guest-name">
                                    Name
                                </Label>
                                <Input
                                    id="attendee-guest-name"
                                    value={form.data.guest_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'guest_name',
                                            event.target.value,
                                        )
                                    }
                                    data-test="attendee-guest-name"
                                />
                                <InputError
                                    message={form.errors.guest_name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="attendee-guest-email">
                                    Email
                                </Label>
                                <Input
                                    id="attendee-guest-email"
                                    type="email"
                                    value={form.data.guest_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'guest_email',
                                            event.target.value,
                                        )
                                    }
                                    data-test="attendee-guest-email"
                                />
                                <InputError
                                    message={form.errors.guest_email}
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="attendee-role">Role</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData(
                                    'role',
                                    value as MeetingAttendeeRole,
                                )
                            }
                        >
                            <SelectTrigger
                                id="attendee-role"
                                data-test="attendee-role"
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
                        <InputError message={form.errors.role} />
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !teamSlug}
                            data-test="attendee-invite-submit"
                        >
                            Invite
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

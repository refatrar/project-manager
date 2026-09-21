import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { store, update } from '@/routes/projects/members';
import type {
    ProjectMember,
    ProjectMemberRole,
    ProjectMemberRoleOption,
    ProjectMemberStatus,
    TeamMemberOption,
} from '@/types';

export type MemberFormData = {
    user_id: string;
    role: ProjectMemberRole;
    status: ProjectMemberStatus;
    allocation_percentage: number;
    hourly_rate: string;
    joined_on: string;
};

export type MemberSavedResponse = {
    member: ProjectMember;
    message: string;
};

type Props = {
    projectId: number;
    member?: ProjectMember | null;
    availableUsers: TeamMemberOption[];
    roleOptions: ProjectMemberRoleOption[];
    onSaved?: (member: ProjectMember, message: string) => void;
    onCancel?: () => void;
};

export default function MemberForm({
    projectId,
    member = null,
    availableUsers,
    roleOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

    const form = useHttp<MemberFormData, MemberSavedResponse>(
        () =>
            member
                ? update.put([teamSlug ?? '', projectId, member.id])
                : store([teamSlug ?? '', projectId]),
        {
            user_id: member ? String(member.user.id) : '',
            role: member?.role ?? 'developer',
            status: member?.status ?? 'active',
            allocation_percentage: member?.allocation_percentage ?? 100,
            hourly_rate: member?.hourly_rate ?? '',
            joined_on: member?.joined_on ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.member, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {!member ? (
                <div className="grid gap-2">
                    <Label htmlFor="member-user">Team member</Label>
                    <Select
                        name="user_id"
                        value={form.data.user_id}
                        onValueChange={(value) => form.setData('user_id', value)}
                    >
                        <SelectTrigger id="member-user" data-test="member-user">
                            <SelectValue placeholder="Select a team member" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableUsers.map((candidate) => (
                                <SelectItem
                                    key={candidate.id}
                                    value={String(candidate.id)}
                                >
                                    {candidate.name} ({candidate.email})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.user_id} />
                </div>
            ) : (
                <div className="grid gap-1">
                    <Label>Team member</Label>
                    <p className="text-sm font-medium">
                        {member.user.name}{' '}
                        <span className="text-muted-foreground">
                            ({member.user.email})
                        </span>
                    </p>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="member-role">Role</Label>
                    <Select
                        name="role"
                        value={form.data.role}
                        onValueChange={(value) =>
                            form.setData('role', value as ProjectMemberRole)
                        }
                    >
                        <SelectTrigger id="member-role" data-test="member-role">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roleOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.role} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="member-allocation">Allocation %</Label>
                    <Input
                        id="member-allocation"
                        type="number"
                        min="0"
                        max="100"
                        value={form.data.allocation_percentage}
                        onChange={(event) =>
                            form.setData(
                                'allocation_percentage',
                                Number(event.target.value),
                            )
                        }
                        data-test="member-allocation"
                    />
                    <InputError message={form.errors.allocation_percentage} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="member-hourly-rate">Hourly rate</Label>
                    <Input
                        id="member-hourly-rate"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.hourly_rate}
                        onChange={(event) =>
                            form.setData('hourly_rate', event.target.value)
                        }
                    />
                    <InputError message={form.errors.hourly_rate} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="member-joined-on">Joined on</Label>
                    <Input
                        id="member-joined-on"
                        type="date"
                        value={form.data.joined_on}
                        onChange={(event) =>
                            form.setData('joined_on', event.target.value)
                        }
                    />
                    <InputError message={form.errors.joined_on} />
                </div>
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="member-submit"
                >
                    {member ? 'Save changes' : 'Add member'}
                </Button>
            </div>
        </form>
    );
}

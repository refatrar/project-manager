import { Pencil, Plus, UserMinus } from 'lucide-react';
import { useState } from 'react';
import MemberFormModal from '@/components/projects/member-form-modal';
import MemberRemoveModal from '@/components/projects/member-remove-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    ProjectMember,
    ProjectMemberCapacity,
    ProjectMemberRoleOption,
    TeamMemberOption,
} from '@/types';

type Props = {
    projectId: number;
    members: ProjectMember[];
    capacity: ProjectMemberCapacity[];
    availableUsers: TeamMemberOption[];
    roleOptions: ProjectMemberRoleOption[];
    canManage: boolean;
    onChanged: () => void;
};

const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function ScheduleTooltip({ entry }: { entry: ProjectMemberCapacity }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Badge
                    variant="outline"
                    className="cursor-default"
                    data-test="member-capacity"
                >
                    {entry.available_hours_14d}h available (14d, this project)
                </Badge>
            </TooltipTrigger>
            <TooltipContent>
                <div className="space-y-1">
                    <p className="font-medium">Weekly schedule</p>
                    {entry.schedule.map((day) => (
                        <p key={day.day_of_week}>
                            {DAY_NAMES[day.day_of_week - 1]}:{' '}
                            {day.is_working_day
                                ? `${day.capacity_hours}h`
                                : 'off'}
                        </p>
                    ))}
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

export default function MemberList({
    projectId,
    members,
    capacity,
    availableUsers,
    roleOptions,
    canManage,
    onChanged,
}: Props) {
    const [editingMember, setEditingMember] = useState<ProjectMember | null>(
        null,
    );
    const [removingMember, setRemovingMember] = useState<ProjectMember | null>(
        null,
    );

    return (
        <div className="space-y-4">
            {canManage ? (
                <div className="flex justify-end">
                    <MemberFormModal
                        projectId={projectId}
                        availableUsers={availableUsers}
                        roleOptions={roleOptions}
                        onSaved={onChanged}
                    >
                        <Button
                            type="button"
                            size="sm"
                            disabled={availableUsers.length === 0}
                            data-test="member-add-button"
                        >
                            <Plus className="h-4 w-4" /> Add member
                        </Button>
                    </MemberFormModal>
                </div>
            ) : null}

            {members.length > 0 ? (
                <div className="space-y-2">
                    {members.map((member) => (
                        <div
                            key={member.id}
                            data-test="member-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {member.user.name}
                                    </span>
                                    <Badge variant="secondary">
                                        {member.role.replace('_', ' ')}
                                    </Badge>
                                    {member.status === 'inactive' ? (
                                        <Badge variant="outline">
                                            Inactive
                                        </Badge>
                                    ) : null}
                                    {(() => {
                                        const entry = capacity.find(
                                            (row) =>
                                                row.user_id === member.user.id,
                                        );

                                        return entry ? (
                                            <ScheduleTooltip entry={entry} />
                                        ) : null;
                                    })()}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {member.user.email} ·{' '}
                                    {member.allocation_percentage}% allocated
                                </p>
                            </div>

                            {canManage ? (
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setEditingMember(member)}
                                        data-test="member-edit"
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setRemovingMember(member)
                                        }
                                        data-test="member-remove"
                                    >
                                        <UserMinus className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No members yet. Add a team member to get started.
                </p>
            )}

            <MemberFormModal
                open={editingMember !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingMember(null);
                    }
                }}
                projectId={projectId}
                member={editingMember}
                availableUsers={availableUsers}
                roleOptions={roleOptions}
                onSaved={onChanged}
            />

            <MemberRemoveModal
                projectId={projectId}
                member={removingMember}
                open={removingMember !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setRemovingMember(null);
                    }
                }}
                onRemoved={onChanged}
            />
        </div>
    );
}

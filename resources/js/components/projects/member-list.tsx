import { Pencil, Plus, UserMinus } from 'lucide-react';
import { useState } from 'react';
import MemberFormModal from '@/components/projects/member-form-modal';
import MemberRemoveModal from '@/components/projects/member-remove-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    ProjectMember,
    ProjectMemberRoleOption,
    TeamMemberOption,
} from '@/types';

type Props = {
    projectId: number;
    members: ProjectMember[];
    availableUsers: TeamMemberOption[];
    roleOptions: ProjectMemberRoleOption[];
    onChanged: () => void;
};

export default function MemberList({
    projectId,
    members,
    availableUsers,
    roleOptions,
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
                                        <Badge variant="outline">Inactive</Badge>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {member.user.email} ·{' '}
                                    {member.allocation_percentage}% allocated
                                </p>
                            </div>

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
                                    onClick={() => setRemovingMember(member)}
                                    data-test="member-remove"
                                >
                                    <UserMinus className="h-4 w-4" />
                                </Button>
                            </div>
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

import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import MemberForm from '@/components/projects/member-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    ProjectMember,
    ProjectMemberRoleOption,
    TeamMemberOption,
} from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    projectId: number;
    member?: ProjectMember | null;
    availableUsers: TeamMemberOption[];
    roleOptions: ProjectMemberRoleOption[];
    onSaved?: (member: ProjectMember) => void;
}>;

export default function MemberFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    member = null,
    availableUsers,
    roleOptions,
    onSaved,
}: Props) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isControlled = open !== undefined;
    const dialogOpen = isControlled ? open : uncontrolledOpen;

    const setDialogOpen = (nextOpen: boolean) => {
        if (!isControlled) {
            setUncontrolledOpen(nextOpen);
        }

        onOpenChange?.(nextOpen);
    };

    const isEditing = Boolean(member);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit member' : 'Add member'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? "Update this member's role and allocation."
                            : 'Add a team member to this project with a role and allocation.'}
                    </DialogDescription>
                </DialogHeader>

                <MemberForm
                    key={`${String(dialogOpen)}-${member?.id ?? 'create'}`}
                    projectId={projectId}
                    member={member}
                    availableUsers={availableUsers}
                    roleOptions={roleOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedMember, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedMember);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

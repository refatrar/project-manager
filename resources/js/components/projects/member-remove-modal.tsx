import { useHttp, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/projects/members';
import type { ProjectMember } from '@/types';

type RemovedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    member: ProjectMember | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onRemoved?: (member: ProjectMember) => void;
};

export default function MemberRemoveModal({
    projectId,
    member,
    open,
    onOpenChange,
    onRemoved,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, RemovedResponse>({});

    const removeMember = () => {
        if (!member || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, member.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onRemoved?.(member);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove member</DialogTitle>
                    <DialogDescription>
                        Remove <strong>{member?.user.name}</strong> from this
                        project? Their history on the project is kept, and
                        they can be re-added later.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="member-remove-confirm"
                        disabled={form.processing || !member}
                        onClick={removeMember}
                    >
                        Remove member
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

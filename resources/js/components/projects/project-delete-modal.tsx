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
import { destroy } from '@/routes/projects';
import type { Project } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    project: Project | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (project: Project) => void;
};

export default function ProjectDeleteModal({
    project,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteProject = () => {
        if (!project || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, project.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(project);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete project</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{project?.name}</strong>? This removes it from
                        every list; its history is kept but the project can
                        only be recovered by a developer.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="project-delete-confirm"
                        disabled={form.processing || !project}
                        onClick={deleteProject}
                    >
                        Delete project
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

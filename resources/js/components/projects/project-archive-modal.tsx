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
import { archive } from '@/routes/projects';
import type { Project, ProjectDetail } from '@/types';

type ArchivedResponse = {
    project: ProjectDetail;
    message: string;
};

type Props = {
    project: Project | ProjectDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onArchived?: (project: ProjectDetail) => void;
};

export default function ProjectArchiveModal({
    project,
    open,
    onOpenChange,
    onArchived,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, ArchivedResponse>({});

    const archiveProject = () => {
        if (!project || !teamSlug) {
            return;
        }

        void form.patch(archive.url([teamSlug, project.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onArchived?.(response.project);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive project</DialogTitle>
                    <DialogDescription>
                        Archiving <strong>{project?.name}</strong> keeps its
                        full history but removes it from the active project
                        lists.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="project-archive-confirm"
                        disabled={form.processing || !project}
                        onClick={archiveProject}
                    >
                        Archive project
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

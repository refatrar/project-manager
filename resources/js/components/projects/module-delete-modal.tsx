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
import { destroy } from '@/routes/projects/modules';
import type { ProjectModule } from '@/types';

type DeletedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    module: ProjectModule | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (module: ProjectModule) => void;
};

export default function ModuleDeleteModal({
    projectId,
    module,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<Record<string, never>, DeletedResponse>({});

    const deleteModule = () => {
        if (!module || !teamSlug) {
            return;
        }

        void form.delete(destroy.url([teamSlug, projectId, module.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onOpenChange(false);
                onDeleted?.(module);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete module</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{module?.name}</strong>? Any of its tasks and
                        sub-modules are kept but lose this grouping.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="module-delete-confirm"
                        disabled={form.processing || !module}
                        onClick={deleteModule}
                    >
                        Delete module
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

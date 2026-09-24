import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ProjectForm from '@/components/projects/project-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    PriorityOption,
    Project,
    ProjectDetail,
    ProjectHealthOption,
    ProjectStatusOption,
    TeamMemberOption,
} from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    project?: Project | ProjectDetail | null;
    statusOptions: ProjectStatusOption[];
    priorityOptions: PriorityOption[];
    healthOptions: ProjectHealthOption[];
    teamMembers: TeamMemberOption[];
    onSaved?: (project: ProjectDetail) => void;
}>;

export default function ProjectFormModal({
    children,
    open,
    onOpenChange,
    project = null,
    statusOptions,
    priorityOptions,
    healthOptions,
    teamMembers,
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

    const isEditing = Boolean(project);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit project' : 'Create project'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the plan for this project.'
                            : 'Set up a new project for this team. You become its owner.'}
                    </DialogDescription>
                </DialogHeader>

                <ProjectForm
                    key={`${String(dialogOpen)}-${project?.id ?? 'create'}`}
                    project={project}
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    healthOptions={healthOptions}
                    teamMembers={teamMembers}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedProject, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedProject);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

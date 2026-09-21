import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ModuleForm from '@/components/projects/module-form';
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
    ProjectModule,
    ProjectModuleStatusOption,
} from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    projectId: number;
    module?: ProjectModule | null;
    defaultParentId?: number | null;
    availableParents: ProjectModule[];
    statusOptions: ProjectModuleStatusOption[];
    priorityOptions: PriorityOption[];
    onSaved?: (module: ProjectModule) => void;
}>;

export default function ModuleFormModal({
    children,
    open,
    onOpenChange,
    projectId,
    module = null,
    defaultParentId = null,
    availableParents,
    statusOptions,
    priorityOptions,
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

    const isEditing = Boolean(module);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit module' : 'Add module'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update this module and where it sits in the breakdown.'
                            : 'Break the project down into a module. It can nest under another module.'}
                    </DialogDescription>
                </DialogHeader>

                <ModuleForm
                    key={`${String(dialogOpen)}-${module?.id ?? 'create'}-${defaultParentId ?? 'root'}`}
                    projectId={projectId}
                    module={module}
                    defaultParentId={defaultParentId}
                    availableParents={availableParents}
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedModule, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedModule);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}

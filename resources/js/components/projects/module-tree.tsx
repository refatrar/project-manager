import { useHttp, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import ModuleDeleteModal from '@/components/projects/module-delete-modal';
import ModuleFormModal from '@/components/projects/module-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { reorder } from '@/routes/projects/modules';
import type {
    PriorityOption,
    ProjectModule,
    ProjectModuleStatusOption,
} from '@/types';

type ReorderedResponse = {
    modules: ProjectModule[];
    message: string;
};

type ReorderFormData = {
    modules: Array<{ id: number; parent_id: number | null; position: number }>;
};

type Props = {
    projectId: number;
    modules: ProjectModule[];
    statusOptions: ProjectModuleStatusOption[];
    priorityOptions: PriorityOption[];
    onChanged: () => void;
};

export default function ModuleTree({
    projectId,
    modules,
    statusOptions,
    priorityOptions,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const reorderForm = useHttp<ReorderFormData, ReorderedResponse>({
        modules: [],
    });

    const [addingUnderParentId, setAddingUnderParentId] = useState<
        number | null | undefined
    >(undefined);
    const [editingModule, setEditingModule] = useState<ProjectModule | null>(
        null,
    );
    const [deletingModule, setDeletingModule] = useState<ProjectModule | null>(
        null,
    );

    const byParent = useMemo(() => {
        const groups = new Map<number | null, ProjectModule[]>();

        for (const module of modules) {
            const key = module.parent_id;
            const siblings = groups.get(key) ?? [];
            siblings.push(module);
            groups.set(key, siblings);
        }

        for (const siblings of groups.values()) {
            siblings.sort((a, b) => a.position - b.position);
        }

        return groups;
    }, [modules]);

    const moveWithinSiblings = (module: ProjectModule, direction: -1 | 1) => {
        if (!teamSlug) {
            return;
        }

        const siblings = byParent.get(module.parent_id) ?? [];
        const index = siblings.findIndex((sibling) => sibling.id === module.id);
        const swapWith = siblings[index + direction];

        if (!swapWith) {
            return;
        }

        reorderForm.transform(() => ({
            modules: [
                {
                    id: module.id,
                    parent_id: module.parent_id,
                    position: swapWith.position,
                },
                {
                    id: swapWith.id,
                    parent_id: swapWith.parent_id,
                    position: module.position,
                },
            ],
        }));

        void reorderForm.patch(reorder.url([teamSlug, projectId]), {
            onSuccess: () => onChanged(),
            onError: () => toast.error('Could not reorder that module.'),
        });
    };

    const renderNode = (module: ProjectModule, depth: number) => {
        const children = byParent.get(module.id) ?? [];
        const siblings = byParent.get(module.parent_id) ?? [];
        const index = siblings.findIndex((sibling) => sibling.id === module.id);

        return (
            <div key={module.id} data-test="module-node">
                <div
                    className="flex items-center justify-between gap-3 rounded-lg border p-3"
                    style={{ marginLeft: depth * 24 }}
                >
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{module.name}</span>
                            <Badge variant="secondary">
                                {module.status.replace('_', ' ')}
                            </Badge>
                            <span className="text-muted-foreground text-xs">
                                {module.priority} priority ·{' '}
                                {module.progress_percentage}%
                            </span>
                        </div>
                        {module.description ? (
                            <p className="text-muted-foreground mt-1 text-sm">
                                {module.description}
                            </p>
                        ) : null}
                    </div>

                    <div className="flex shrink-0 items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={index <= 0}
                            onClick={() => moveWithinSiblings(module, -1)}
                            data-test="module-move-up"
                        >
                            <ChevronUp className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={index === -1 || index >= siblings.length - 1}
                            onClick={() => moveWithinSiblings(module, 1)}
                            data-test="module-move-down"
                        >
                            <ChevronDown className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setAddingUnderParentId(module.id)}
                            data-test="module-add-child"
                        >
                            <Plus className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditingModule(module)}
                            data-test="module-edit"
                        >
                            <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeletingModule(module)}
                            data-test="module-delete"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {children.length > 0 ? (
                    <div className="mt-2 space-y-2">
                        {children.map((child) => renderNode(child, depth + 1))}
                    </div>
                ) : null}
            </div>
        );
    };

    const roots = byParent.get(null) ?? [];

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <ModuleFormModal
                    projectId={projectId}
                    defaultParentId={null}
                    availableParents={modules}
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    onSaved={onChanged}
                >
                    <Button type="button" size="sm" data-test="module-add-root">
                        <Plus className="h-4 w-4" /> Add module
                    </Button>
                </ModuleFormModal>
            </div>

            {roots.length > 0 ? (
                <div className="space-y-2">
                    {roots.map((module) => renderNode(module, 0))}
                </div>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No modules yet. Break this project down to plan the work.
                </p>
            )}

            <ModuleFormModal
                open={addingUnderParentId !== undefined}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setAddingUnderParentId(undefined);
                    }
                }}
                projectId={projectId}
                defaultParentId={addingUnderParentId ?? null}
                availableParents={modules}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={onChanged}
            />

            <ModuleFormModal
                open={editingModule !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingModule(null);
                    }
                }}
                projectId={projectId}
                module={editingModule}
                availableParents={modules}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={onChanged}
            />

            <ModuleDeleteModal
                projectId={projectId}
                module={deletingModule}
                open={deletingModule !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDeletingModule(null);
                    }
                }}
                onDeleted={onChanged}
            />
        </div>
    );
}

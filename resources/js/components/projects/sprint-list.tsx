import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SprintDeleteModal from '@/components/projects/sprint-delete-modal';
import SprintFormModal from '@/components/projects/sprint-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Sprint, SprintStatusOption } from '@/types';

type Props = {
    projectId: number;
    sprints: Sprint[];
    statusOptions: SprintStatusOption[];
    canManage: boolean;
    onChanged: () => void;
};

export default function SprintList({
    projectId,
    sprints,
    statusOptions,
    canManage,
    onChanged,
}: Props) {
    const [editingSprint, setEditingSprint] = useState<Sprint | null>(null);
    const [deletingSprint, setDeletingSprint] = useState<Sprint | null>(null);

    return (
        <div className="space-y-4">
            {canManage ? (
                <div className="flex justify-end">
                    <SprintFormModal
                        projectId={projectId}
                        statusOptions={statusOptions}
                        onSaved={onChanged}
                    >
                        <Button type="button" size="sm" data-test="sprint-add">
                            <Plus className="h-4 w-4" /> Add sprint
                        </Button>
                    </SprintFormModal>
                </div>
            ) : null}

            {sprints.length > 0 ? (
                <div className="space-y-2">
                    {sprints.map((sprint) => {
                        const capacity = Number(sprint.capacity_hours ?? 0);
                        const committed = Number(sprint.committed_hours ?? 0);
                        const overCommitted =
                            capacity > 0 && committed > capacity;

                        return (
                            <div
                                key={sprint.id}
                                data-test="sprint-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-3"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {sprint.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {sprint.status}
                                        </Badge>
                                        {overCommitted ? (
                                            <Badge variant="destructive">
                                                Over capacity
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {sprint.starts_on} – {sprint.ends_on}
                                        {sprint.capacity_hours
                                            ? ` · ${sprint.committed_hours ?? 0}h committed of ${sprint.capacity_hours}h capacity`
                                            : null}
                                    </p>
                                </div>

                                {canManage ? (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setEditingSprint(sprint)
                                            }
                                            data-test="sprint-edit"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setDeletingSprint(sprint)
                                            }
                                            data-test="sprint-delete"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ) : null}
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No sprints yet.
                </p>
            )}

            <SprintFormModal
                open={editingSprint !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingSprint(null);
                    }
                }}
                projectId={projectId}
                sprint={editingSprint}
                statusOptions={statusOptions}
                onSaved={onChanged}
            />

            <SprintDeleteModal
                projectId={projectId}
                sprint={deletingSprint}
                open={deletingSprint !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDeletingSprint(null);
                    }
                }}
                onDeleted={onChanged}
            />
        </div>
    );
}

import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import MilestoneDeleteModal from '@/components/projects/milestone-delete-modal';
import MilestoneFormModal from '@/components/projects/milestone-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Milestone, MilestoneStatusOption } from '@/types';

type Props = {
    projectId: number;
    milestones: Milestone[];
    statusOptions: MilestoneStatusOption[];
    onChanged: () => void;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'outline',
    in_progress: 'secondary',
    completed: 'default',
    missed: 'destructive',
    cancelled: 'destructive',
};

export default function MilestoneList({
    projectId,
    milestones,
    statusOptions,
    onChanged,
}: Props) {
    const [editingMilestone, setEditingMilestone] = useState<Milestone | null>(
        null,
    );
    const [deletingMilestone, setDeletingMilestone] =
        useState<Milestone | null>(null);

    // A chronological list is the "timeline": due dates sorted ascending,
    // with undated milestones trailing. No Gantt-style visualization yet.
    const sorted = [...milestones].sort((a, b) => {
        if (!a.due_on && !b.due_on) {
            return 0;
        }
        if (!a.due_on) {
            return 1;
        }
        if (!b.due_on) {
            return -1;
        }
        return a.due_on.localeCompare(b.due_on);
    });

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <MilestoneFormModal
                    projectId={projectId}
                    statusOptions={statusOptions}
                    onSaved={onChanged}
                >
                    <Button type="button" size="sm" data-test="milestone-add">
                        <Plus className="h-4 w-4" /> Add milestone
                    </Button>
                </MilestoneFormModal>
            </div>

            {sorted.length > 0 ? (
                <div className="space-y-2">
                    {sorted.map((milestone) => (
                        <div
                            key={milestone.id}
                            data-test="milestone-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {milestone.name}
                                    </span>
                                    <Badge
                                        variant={
                                            statusVariant[milestone.status] ??
                                            'outline'
                                        }
                                    >
                                        {milestone.status.replace('_', ' ')}
                                    </Badge>
                                    {milestone.is_billable ? (
                                        <Badge variant="secondary">
                                            Billable
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Due {milestone.due_on ?? 'unscheduled'} ·{' '}
                                    {milestone.progress_percentage}% complete
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setEditingMilestone(milestone)}
                                    data-test="milestone-edit"
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setDeletingMilestone(milestone)
                                    }
                                    data-test="milestone-delete"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No milestones yet.
                </p>
            )}

            <MilestoneFormModal
                open={editingMilestone !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingMilestone(null);
                    }
                }}
                projectId={projectId}
                milestone={editingMilestone}
                statusOptions={statusOptions}
                onSaved={onChanged}
            />

            <MilestoneDeleteModal
                projectId={projectId}
                milestone={deletingMilestone}
                open={deletingMilestone !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDeletingMilestone(null);
                    }
                }}
                onDeleted={onChanged}
            />
        </div>
    );
}

import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AllocationDeleteModal from '@/components/projects/allocation-delete-modal';
import AllocationFormModal from '@/components/projects/allocation-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AllocationStatusOption, ProjectMember, ResourceAllocation } from '@/types';

type Props = {
    projectId: number;
    allocations: ResourceAllocation[];
    members: ProjectMember[];
    statusOptions: AllocationStatusOption[];
    onChanged: () => void;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    planned: 'outline',
    confirmed: 'secondary',
    completed: 'default',
    cancelled: 'destructive',
};

export default function AllocationList({
    projectId,
    allocations,
    members,
    statusOptions,
    onChanged,
}: Props) {
    const [editingAllocation, setEditingAllocation] = useState<ResourceAllocation | null>(null);
    const [deletingAllocation, setDeletingAllocation] = useState<ResourceAllocation | null>(null);

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <AllocationFormModal
                    projectId={projectId}
                    members={members}
                    statusOptions={statusOptions}
                    onSaved={onChanged}
                >
                    <Button type="button" size="sm" data-test="allocation-add">
                        <Plus className="h-4 w-4" /> Book hours
                    </Button>
                </AllocationFormModal>
            </div>

            {allocations.length > 0 ? (
                <div className="space-y-2">
                    {allocations.map((allocation) => (
                        <div
                            key={allocation.id}
                            data-test="allocation-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{allocation.user.name}</span>
                                    <Badge variant={statusVariant[allocation.status] ?? 'outline'}>
                                        {allocation.status}
                                    </Badge>
                                    {allocation.task ? (
                                        <span className="text-muted-foreground text-xs">
                                            #{allocation.task.number} {allocation.task.title}
                                        </span>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {allocation.starts_on} – {allocation.ends_on} ·{' '}
                                    {allocation.hours_per_day}h/day
                                    {allocation.allocation_percentage
                                        ? ` · ${allocation.allocation_percentage}%`
                                        : ''}
                                </p>
                                {allocation.notes ? (
                                    <p className="text-muted-foreground mt-1 text-sm">{allocation.notes}</p>
                                ) : null}
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setEditingAllocation(allocation)}
                                    data-test="allocation-edit"
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setDeletingAllocation(allocation)}
                                    data-test="allocation-delete"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No hours booked yet.
                </p>
            )}

            <AllocationFormModal
                open={editingAllocation !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingAllocation(null);
                    }
                }}
                projectId={projectId}
                allocation={editingAllocation}
                members={members}
                statusOptions={statusOptions}
                onSaved={onChanged}
            />

            <AllocationDeleteModal
                projectId={projectId}
                allocation={deletingAllocation}
                open={deletingAllocation !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDeletingAllocation(null);
                    }
                }}
                onDeleted={onChanged}
            />
        </div>
    );
}

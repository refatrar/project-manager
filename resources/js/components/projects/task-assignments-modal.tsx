import { useHttp, usePage } from '@inertiajs/react';
import { UserMinus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store } from '@/routes/projects/tasks/assignments';
import type {
    ProjectMember,
    Task,
    TaskAssignmentRole,
    TaskAssignmentRoleOption,
} from '@/types';

type AssignedResponse = {
    assignment: { id: number };
    message: string;
};

type RemovedResponse = {
    message: string;
};

type AddFormData = {
    user_id: string;
    role: TaskAssignmentRole;
    allocated_hours: string;
};

type Props = {
    projectId: number;
    task: Task | null;
    members: ProjectMember[];
    roleOptions: TaskAssignmentRoleOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onChanged: () => void;
};

export default function TaskAssignmentsModal({
    projectId,
    task,
    members,
    roleOptions,
    open,
    onOpenChange,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [userId, setUserId] = useState('');
    const [role, setRole] = useState<TaskAssignmentRole>('assignee');
    const [allocatedHours, setAllocatedHours] = useState('');

    const addForm = useHttp<AddFormData, AssignedResponse>({
        user_id: '',
        role: 'assignee',
        allocated_hours: '',
    });
    const removeForm = useHttp<Record<string, never>, RemovedResponse>({});

    if (!task || !teamSlug) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent />
            </Dialog>
        );
    }

    const addAssignment = () => {
        if (!userId) {
            return;
        }

        addForm.transform(() => ({
            user_id: userId,
            role,
            allocated_hours: allocatedHours,
        }));

        void addForm.post(store.url([teamSlug, projectId, task.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                setUserId('');
                setAllocatedHours('');
                onChanged();
            },
            onError: () => toast.error('Could not add that assignment.'),
        });
    };

    const removeAssignment = (assignmentId: number) => {
        void removeForm.delete(
            destroy.url([teamSlug, projectId, task.id, assignmentId]),
            {
                onSuccess: (response) => {
                    toast.success(response.message);
                    onChanged();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assignments</DialogTitle>
                    <DialogDescription>
                        {task.reference} — {task.title}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    {task.assignments.length > 0 ? (
                        task.assignments.map((assignment) => (
                            <div
                                key={assignment.id}
                                data-test="assignment-row"
                                className="flex items-center justify-between gap-2 rounded-lg border p-2"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">
                                        {assignment.user.name}
                                    </span>
                                    <Badge variant="secondary">
                                        {assignment.role}
                                    </Badge>
                                    {assignment.allocated_hours ? (
                                        <span className="text-muted-foreground text-xs">
                                            {assignment.allocated_hours}h
                                        </span>
                                    ) : null}
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    disabled={removeForm.processing}
                                    onClick={() =>
                                        removeAssignment(assignment.id)
                                    }
                                    data-test="assignment-remove"
                                >
                                    <UserMinus className="h-4 w-4" />
                                </Button>
                            </div>
                        ))
                    ) : (
                        <p className="text-muted-foreground py-4 text-center text-sm">
                            Nobody is assigned yet.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 border-t pt-4">
                    <div className="grid gap-2">
                        <Label htmlFor="assignment-user">Member</Label>
                        <Select value={userId} onValueChange={setUserId}>
                            <SelectTrigger
                                id="assignment-user"
                                data-test="assignment-user"
                            >
                                <SelectValue placeholder="Select a project member" />
                            </SelectTrigger>
                            <SelectContent>
                                {members.map((member) => (
                                    <SelectItem
                                        key={member.user.id}
                                        value={String(member.user.id)}
                                    >
                                        {member.user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="assignment-role">Role</Label>
                            <Select
                                value={role}
                                onValueChange={(value) =>
                                    setRole(value as TaskAssignmentRole)
                                }
                            >
                                <SelectTrigger
                                    id="assignment-role"
                                    data-test="assignment-role"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roleOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="assignment-hours">
                                Allocated hours
                            </Label>
                            <Input
                                id="assignment-hours"
                                type="number"
                                min="0"
                                step="0.01"
                                value={allocatedHours}
                                onChange={(event) =>
                                    setAllocatedHours(event.target.value)
                                }
                            />
                        </div>
                    </div>

                    <Button
                        type="button"
                        disabled={!userId || addForm.processing}
                        onClick={addAssignment}
                        data-test="assignment-add"
                    >
                        Add assignment
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

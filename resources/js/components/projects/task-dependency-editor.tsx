import { Link, useHttp, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showTask } from '@/routes/projects/tasks';
import { destroy, store } from '@/routes/projects/tasks/dependencies';
import type {
    TaskDependencyItem,
    TaskDependencyType,
    TaskDependencyTypeOption,
    TaskReference,
} from '@/types';

type AddedResponse = {
    dependency: TaskDependencyItem;
    message: string;
};

type RemovedResponse = {
    message: string;
};

type Props = {
    projectId: number;
    taskId: number;
    dependencies: TaskDependencyItem[];
    candidates: TaskReference[];
    typeOptions: TaskDependencyTypeOption[];
    canManage: boolean;
    onChanged: () => void;
};

export default function TaskDependencyEditor({
    projectId,
    taskId,
    dependencies,
    candidates,
    typeOptions,
    canManage,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [relatedTaskId, setRelatedTaskId] = useState('');
    const [type, setType] = useState<TaskDependencyType>('blocked_by');

    const addForm = useHttp<
        { related_task_id: string; type: TaskDependencyType },
        AddedResponse
    >({ related_task_id: '', type: 'blocked_by' });
    const removeForm = useHttp<Record<string, never>, RemovedResponse>({});

    if (!teamSlug) {
        return null;
    }

    const addDependency = () => {
        if (!relatedTaskId) {
            return;
        }

        addForm.transform(() => ({ related_task_id: relatedTaskId, type }));

        void addForm.post(store.url([teamSlug, projectId, taskId]), {
            onSuccess: () => {
                setRelatedTaskId('');
                onChanged();
            },
            onError: (errors) => {
                toast.error(
                    errors.related_task_id ?? 'Could not add that dependency.',
                );
            },
        });
    };

    const removeDependency = (dependencyId: number) => {
        void removeForm.delete(
            destroy.url([teamSlug, projectId, taskId, dependencyId]),
            {
                onSuccess: (response) => {
                    toast.success(response.message);
                    onChanged();
                },
            },
        );
    };

    return (
        <div className="space-y-3">
            {dependencies.length > 0 ? (
                <div className="space-y-2">
                    {dependencies.map((dependency) => (
                        <div
                            key={dependency.id}
                            data-test="dependency-row"
                            className="flex items-center justify-between gap-2 rounded-lg border p-2"
                        >
                            <div className="flex items-center gap-2 text-sm">
                                <Badge variant="secondary">
                                    {dependency.type.replace('_', ' ')}
                                </Badge>
                                <Link
                                    href={showTask.url([
                                        teamSlug,
                                        projectId,
                                        dependency.relatedTask.id,
                                    ])}
                                    className="text-muted-foreground hover:text-foreground font-mono"
                                >
                                    {dependency.relatedTask.reference}
                                </Link>
                                <span>{dependency.relatedTask.title}</span>
                            </div>
                            {canManage ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    disabled={removeForm.processing}
                                    onClick={() =>
                                        removeDependency(dependency.id)
                                    }
                                    data-test="dependency-remove"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    No dependencies declared.
                </p>
            )}

            {canManage ? (
                <div className="flex flex-wrap items-end gap-2 border-t pt-3">
                    <div className="grid gap-1">
                        <Select
                            value={type}
                            onValueChange={(value) =>
                                setType(value as TaskDependencyType)
                            }
                        >
                            <SelectTrigger
                                className="w-40"
                                data-test="dependency-type"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {typeOptions.map((option) => (
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

                    <div className="grid gap-1">
                        <Select
                            value={relatedTaskId}
                            onValueChange={setRelatedTaskId}
                        >
                            <SelectTrigger
                                className="w-56"
                                data-test="dependency-task"
                            >
                                <SelectValue placeholder="Select a task" />
                            </SelectTrigger>
                            <SelectContent>
                                {candidates.map((candidate) => (
                                    <SelectItem
                                        key={candidate.id}
                                        value={String(candidate.id)}
                                    >
                                        {candidate.reference} —{' '}
                                        {candidate.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        type="button"
                        disabled={!relatedTaskId || addForm.processing}
                        onClick={addDependency}
                        data-test="dependency-add"
                    >
                        Add
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

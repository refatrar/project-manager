import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Pagination } from '@/components/patterns/pagination';
import Heading from '@/components/heading';
import TaskTypeDeleteModal from '@/components/setup/task-type-delete-modal';
import TaskTypeFormModal from '@/components/setup/task-type-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/setup/task-types';
import type { Paginated, ScopeStatusOption, TaskType } from '@/types';

type Props = {
    taskTypes: Paginated<TaskType>;
    statusOptions: ScopeStatusOption[];
};

export default function TaskTypesIndex({ taskTypes, statusOptions }: Props) {
    const [editingScope, setEditingScope] = useState<TaskType | null>(null);
    const [scopeToDelete, setScopeToDelete] = useState<TaskType | null>(null);

    const refreshScopes = () => {
        router.reload({ only: ['taskTypes'] });
    };

    return (
        <>
            <Head title="Task types" />

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Task types"
                        description="Manage reusable task types."
                    />

                    <TaskTypeFormModal
                        statusOptions={statusOptions}
                        onSaved={refreshScopes}
                    >
                        <Button
                            type="button"
                            data-test="task-types-create-button"
                        >
                            <Plus /> New task type
                        </Button>
                    </TaskTypeFormModal>
                </div>

                <div className="bg-card divide-y overflow-hidden rounded-lg border">
                    {taskTypes.data.map((scope) => (
                        <div
                            key={scope.id}
                            data-test="task-type-row"
                            className="hover:bg-accent/40 flex items-center justify-between gap-4 px-4 py-3.5 transition-colors"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {scope.name}
                                    </span>
                                    <Badge
                                        variant={
                                            scope.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {scope.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </div>
                                {scope.description ? (
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {scope.description}
                                    </p>
                                ) : null}
                            </div>

                            <TooltipProvider>
                                <div className="flex items-center gap-2">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Edit task type"
                                                data-test="edit-task-type"
                                                onClick={() =>
                                                    setEditingScope(scope)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Edit task type</p>
                                        </TooltipContent>
                                    </Tooltip>

                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Delete task type"
                                                data-test="delete-task-type"
                                                onClick={() =>
                                                    setScopeToDelete(scope)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete task type</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </TooltipProvider>
                        </div>
                    ))}

                    {taskTypes.data.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-10 text-center text-sm">
                            No task types have been created yet.
                        </p>
                    ) : null}
                </div>

                <Pagination paginator={taskTypes} />
            </div>

            <TaskTypeFormModal
                open={editingScope !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingScope(null);
                    }
                }}
                taskType={editingScope}
                statusOptions={statusOptions}
                onSaved={refreshScopes}
            />

            <TaskTypeDeleteModal
                taskType={scopeToDelete}
                open={scopeToDelete !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setScopeToDelete(null);
                    }
                }}
                onDeleted={refreshScopes}
            />
        </>
    );
}

TaskTypesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Task types',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

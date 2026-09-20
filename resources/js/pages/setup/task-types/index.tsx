import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
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

                <div className="space-y-3">
                    {taskTypes.data.map((scope) => (
                        <div
                            key={scope.id}
                            data-test="task-type-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
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
                                                size="sm"
                                                data-test="edit-task-type"
                                                onClick={() =>
                                                    setEditingScope(scope)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
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
                                                size="sm"
                                                data-test="delete-task-type"
                                                onClick={() =>
                                                    setScopeToDelete(scope)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
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
                        <p className="text-muted-foreground py-8 text-center">
                            No task types have been created yet.
                        </p>
                    ) : null}
                </div>

                {taskTypes.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {taskTypes.links.map((link, linkIndex) =>
                            link.url ? (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url} preserveState>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ),
                        )}
                    </div>
                ) : null}
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

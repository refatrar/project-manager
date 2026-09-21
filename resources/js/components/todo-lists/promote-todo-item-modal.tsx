import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent, PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { promote } from '@/routes/todo-lists/items';
import type { ProjectOption, TaskTypeOption, TodoItem } from '@/types';

type PromotedResponse = {
    item: TodoItem;
    message: string;
};

type Props = PropsWithChildren<{
    todoListId: number;
    item: TodoItem;
    taskTypes: TaskTypeOption[];
    projects?: ProjectOption[];
    onPromoted?: (item: TodoItem) => void;
}>;

export default function PromoteTodoItemModal({
    children,
    todoListId,
    item,
    taskTypes,
    projects,
    onPromoted,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [open, setOpen] = useState(false);
    const needsProject = projects !== undefined;
    const form = useHttp<{ task_type_id: string; project_id: string }, PromotedResponse>(
        () => promote([teamSlug ?? '', todoListId, item.id]),
        {
            task_type_id: '',
            project_id: '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                toast.success(response.message);
                setOpen(false);
                onPromoted?.(response.item);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Promote to task</DialogTitle>
                    <DialogDescription>
                        {needsProject
                            ? 'Turn this item into a task on a project.'
                            : "Turn this item into a subtask of this task."}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-6">
                    {needsProject ? (
                        <div className="grid gap-2">
                            <Label htmlFor="promote-project">Project</Label>
                            <Select
                                value={form.data.project_id}
                                onValueChange={(value) =>
                                    form.setData('project_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="promote-project"
                                    data-test="promote-project"
                                >
                                    <SelectValue placeholder="Select a project" />
                                </SelectTrigger>
                                <SelectContent>
                                    {projects?.map((project) => (
                                        <SelectItem
                                            key={project.id}
                                            value={String(project.id)}
                                        >
                                            {project.code} · {project.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    ) : null}

                    <div className="grid gap-2">
                        <Label htmlFor="promote-task-type">Task type</Label>
                        <Select
                            value={form.data.task_type_id}
                            onValueChange={(value) =>
                                form.setData('task_type_id', value)
                            }
                        >
                            <SelectTrigger
                                id="promote-task-type"
                                data-test="promote-task-type"
                            >
                                <SelectValue placeholder="Select a task type" />
                            </SelectTrigger>
                            <SelectContent>
                                {taskTypes.map((taskType) => (
                                    <SelectItem
                                        key={taskType.id}
                                        value={String(taskType.id)}
                                    >
                                        {taskType.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !teamSlug ||
                                !form.data.task_type_id ||
                                (needsProject && !form.data.project_id)
                            }
                            data-test="promote-submit"
                        >
                            Create task
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

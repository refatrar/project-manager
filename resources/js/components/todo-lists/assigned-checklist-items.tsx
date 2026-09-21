import { Link, useHttp, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { toast } from 'sonner';
import { Checkbox } from '@/components/ui/checkbox';
import { show as showTask } from '@/routes/projects/tasks';
import { toggle } from '@/routes/todo-lists/items';
import type { TodoItem } from '@/types';

type ToggledResponse = {
    item: TodoItem;
};

type Props = {
    items: TodoItem[];
    onChanged: () => void;
};

export default function AssignedChecklistItems({ items, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const toggleForm = useHttp<{ is_completed: boolean }, ToggledResponse>({
        is_completed: false,
    });

    const completeItem = (item: TodoItem) => {
        if (!teamSlug) {
            return;
        }

        toggleForm.transform(() => ({ is_completed: true }));

        void toggleForm.patch(
            toggle.url([teamSlug, item.todo_list_id, item.id]),
            {
                onSuccess: onChanged,
                onError: () => toast.error('Could not update that item.'),
            },
        );
    };

    if (items.length === 0) {
        return (
            <p className="text-muted-foreground py-4 text-center text-sm">
                No checklist items assigned to you.
            </p>
        );
    }

    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <li
                    key={item.id}
                    data-test="assigned-checklist-item-row"
                    className="flex items-start gap-3 rounded-lg border p-3"
                >
                    <Checkbox
                        checked={item.is_completed}
                        onCheckedChange={() => completeItem(item)}
                        data-test="assigned-checklist-item-toggle"
                        className="mt-0.5"
                    />

                    <div className="min-w-0 flex-1">
                        <p className="text-sm">{item.title}</p>
                        <p className="text-muted-foreground mt-0.5 flex flex-wrap gap-x-2 text-xs">
                            {item.due_at ? (
                                <span>Due {item.due_at.slice(0, 10)}</span>
                            ) : null}
                            {item.task && teamSlug ? (
                                <Link
                                    href={showTask.url([
                                        teamSlug,
                                        item.task.project_id,
                                        item.task.id,
                                    ])}
                                    data-test="assigned-checklist-item-task-link"
                                    className="text-primary inline-flex items-center gap-1 hover:underline"
                                >
                                    <ArrowUpRight className="h-3 w-3" />
                                    {item.task.reference}
                                </Link>
                            ) : null}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}

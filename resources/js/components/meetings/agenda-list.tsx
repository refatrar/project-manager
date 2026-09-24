import { useHttp, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AgendaItemFormModal from '@/components/meetings/agenda-item-form-modal';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { destroy, move, toggle } from '@/routes/meetings/agenda-items';
import type {
    MeetingAgendaItem,
    TaskReference,
    TeamMemberOption,
} from '@/types';

type MovedResponse = {
    agendaItems: MeetingAgendaItem[];
};

type ToggledResponse = {
    agendaItem: MeetingAgendaItem;
};

type Props = {
    meetingId: number;
    agendaItems: MeetingAgendaItem[];
    projectTasks: TaskReference[];
    teamMembers: TeamMemberOption[];
    /** Whether the user may edit the agenda (server `can.update`). */
    canManage: boolean;
    onChanged: () => void;
};

export default function AgendaList({
    meetingId,
    agendaItems,
    projectTasks,
    teamMembers,
    canManage,
    onChanged,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editingItem, setEditingItem] = useState<MeetingAgendaItem | null>(
        null,
    );
    const moveForm = useHttp<{ position: number }, MovedResponse>({
        position: 0,
    });
    const toggleForm = useHttp<{ is_discussed: boolean }, ToggledResponse>({
        is_discussed: false,
    });

    const items = [...agendaItems].sort((a, b) => a.position - b.position);

    const moveItem = (item: MeetingAgendaItem, direction: -1 | 1) => {
        if (!teamSlug) {
            return;
        }

        const index = items.findIndex((row) => row.id === item.id);
        const swapWith = items[index + direction];

        if (!swapWith) {
            return;
        }

        // A genuine two-sided swap, not just copying the target's position
        // onto the moved item: doing only one side ties the two positions
        // together, and which one then sorts first is undefined.
        const itemPosition = item.position;
        const swapWithPosition = swapWith.position;

        moveForm.transform(() => ({ position: swapWithPosition }));
        void moveForm.patch(move.url([teamSlug, meetingId, item.id]), {
            onSuccess: () => {
                moveForm.transform(() => ({ position: itemPosition }));
                void moveForm.patch(
                    move.url([teamSlug, meetingId, swapWith.id]),
                    {
                        onSuccess: onChanged,
                        onError: () =>
                            toast.error('Could not reorder that item.'),
                    },
                );
            },
            onError: () => toast.error('Could not reorder that item.'),
        });
    };

    const toggleDiscussed = (item: MeetingAgendaItem) => {
        if (!teamSlug) {
            return;
        }

        toggleForm.transform(() => ({ is_discussed: !item.is_discussed }));

        void toggleForm.patch(toggle.url([teamSlug, meetingId, item.id]), {
            onSuccess: onChanged,
            onError: () => toast.error('Could not update that item.'),
        });
    };

    const deleteItem = (item: MeetingAgendaItem) => {
        if (!teamSlug) {
            return;
        }

        void toggleForm.delete(destroy.url([teamSlug, meetingId, item.id]), {
            onSuccess: () => {
                toast.success('Agenda item removed.');
                onChanged();
            },
        });
    };

    return (
        <div className="space-y-3">
            {items.length > 0 ? (
                <ul className="space-y-2">
                    {items.map((item, index) => (
                        <li
                            key={item.id}
                            data-test="agenda-item-row"
                            className="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                checked={item.is_discussed}
                                disabled={!canManage}
                                onCheckedChange={() => toggleDiscussed(item)}
                                data-test="agenda-item-toggle"
                                className="mt-0.5"
                            />

                            <div className="min-w-0 flex-1">
                                <p
                                    className={cn(
                                        'text-sm',
                                        item.is_discussed &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {item.title}
                                </p>
                                <p className="text-muted-foreground mt-0.5 flex flex-wrap gap-x-2 text-xs">
                                    {item.duration_minutes ? (
                                        <span>{item.duration_minutes} min</span>
                                    ) : null}
                                    {item.presenter ? (
                                        <span>{item.presenter.name}</span>
                                    ) : null}
                                    {item.task ? (
                                        <span>
                                            {item.task.reference}{' '}
                                            {item.task.title}
                                        </span>
                                    ) : null}
                                </p>
                            </div>

                            {canManage ? (
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        disabled={index === 0}
                                        onClick={() => moveItem(item, -1)}
                                        data-test="agenda-item-move-up"
                                    >
                                        <ChevronUp className="h-3 w-3" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        disabled={index === items.length - 1}
                                        onClick={() => moveItem(item, 1)}
                                        data-test="agenda-item-move-down"
                                    >
                                        <ChevronDown className="h-3 w-3" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="agenda-item-edit"
                                        onClick={() => setEditingItem(item)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        data-test="agenda-item-delete"
                                        onClick={() => deleteItem(item)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-muted-foreground py-4 text-center text-sm">
                    No agenda items yet.
                </p>
            )}

            {canManage ? (
                <>
                    <AgendaItemFormModal
                        meetingId={meetingId}
                        projectTasks={projectTasks}
                        teamMembers={teamMembers}
                        onSaved={onChanged}
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-test="agenda-item-add"
                        >
                            <Plus className="h-4 w-4" /> Add agenda item
                        </Button>
                    </AgendaItemFormModal>

                    <AgendaItemFormModal
                        open={editingItem !== null}
                        onOpenChange={(nextOpen) => {
                            if (!nextOpen) {
                                setEditingItem(null);
                            }
                        }}
                        meetingId={meetingId}
                        item={editingItem}
                        projectTasks={projectTasks}
                        teamMembers={teamMembers}
                        onSaved={onChanged}
                    />
                </>
            ) : null}
        </div>
    );
}

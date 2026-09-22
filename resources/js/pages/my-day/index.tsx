import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import AssignedChecklistItems from '@/components/todo-lists/assigned-checklist-items';
import TodoListCard from '@/components/todo-lists/todo-list-card';
import TodoListDeleteModal from '@/components/todo-lists/todo-list-delete-modal';
import TodoListFormModal from '@/components/todo-lists/todo-list-form-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { myDay } from '@/routes';
import type {
    ProjectOption,
    TaskTypeOption,
    TeamMemberOption,
    TodoItem,
    TodoList,
    TodoListStatusOption,
    TodoListTypeOption,
} from '@/types';

type Props = {
    todoLists: TodoList[];
    checklistItems: TodoItem[];
    teamMembers: TeamMemberOption[];
    projects: ProjectOption[];
    taskTypes: TaskTypeOption[];
    typeOptions: TodoListTypeOption[];
    statusOptions: TodoListStatusOption[];
};

export default function MyDayIndex({
    todoLists,
    checklistItems,
    teamMembers,
    projects,
    taskTypes,
    typeOptions,
    statusOptions,
}: Props) {
    const [editingList, setEditingList] = useState<TodoList | null>(null);
    const [listToDelete, setListToDelete] = useState<TodoList | null>(null);

    const refresh = () => {
        router.reload({ only: ['todoLists', 'checklistItems'] });
    };

    return (
        <>
            <Head title="My Day" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="My Day"
                        description="Everything on your plate today: your own lists and checklist items assigned to you across every project."
                    />

                    <TodoListFormModal
                        typeOptions={typeOptions}
                        statusOptions={statusOptions}
                        onSaved={refresh}
                    >
                        <Button
                            type="button"
                            data-test="my-day-create-list-button"
                        >
                            <Plus /> New list
                        </Button>
                    </TodoListFormModal>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Assigned to you</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AssignedChecklistItems
                            items={checklistItems}
                            onChanged={refresh}
                        />
                    </CardContent>
                </Card>

                {todoLists.length > 0 ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {todoLists.map((list) => (
                            <TodoListCard
                                key={list.id}
                                list={list}
                                teamMembers={teamMembers}
                                projects={projects}
                                taskTypes={taskTypes}
                                onEditList={() => setEditingList(list)}
                                onDeleteList={() => setListToDelete(list)}
                                onChanged={refresh}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground py-8 text-center">
                        No personal lists yet.
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Meeting actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            No meeting action items assigned to you yet.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <TodoListFormModal
                open={editingList !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingList(null);
                    }
                }}
                list={editingList}
                typeOptions={typeOptions}
                statusOptions={statusOptions}
                onSaved={refresh}
            />

            <TodoListDeleteModal
                list={listToDelete}
                open={listToDelete !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setListToDelete(null);
                    }
                }}
                onDeleted={refresh}
            />
        </>
    );
}

MyDayIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'My Day',
            href: props.currentTeam ? myDay(props.currentTeam.slug) : '/',
        },
    ],
});

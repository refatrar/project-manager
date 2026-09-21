import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import TodoListCard from '@/components/todo-lists/todo-list-card';
import TodoListDeleteModal from '@/components/todo-lists/todo-list-delete-modal';
import TodoListFormModal from '@/components/todo-lists/todo-list-form-modal';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/todo-lists';
import type {
    ProjectOption,
    TaskTypeOption,
    TeamMemberOption,
    TodoList,
    TodoListStatusOption,
    TodoListTypeOption,
} from '@/types';

type Props = {
    lists: TodoList[];
    teamMembers: TeamMemberOption[];
    projects: ProjectOption[];
    taskTypes: TaskTypeOption[];
    typeOptions: TodoListTypeOption[];
    statusOptions: TodoListStatusOption[];
};

export default function TodoListsIndex({
    lists,
    teamMembers,
    projects,
    taskTypes,
    typeOptions,
    statusOptions,
}: Props) {
    const [editingList, setEditingList] = useState<TodoList | null>(null);
    const [listToDelete, setListToDelete] = useState<TodoList | null>(null);

    const refresh = () => {
        router.reload({ only: ['lists'] });
    };

    return (
        <>
            <Head title="My To-Dos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="My To-Dos"
                        description="Personal lists and daily plans, private to you."
                    />

                    <TodoListFormModal
                        typeOptions={typeOptions}
                        statusOptions={statusOptions}
                        onSaved={refresh}
                    >
                        <Button type="button" data-test="todo-list-create-button">
                            <Plus /> New list
                        </Button>
                    </TodoListFormModal>
                </div>

                {lists.length > 0 ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {lists.map((list) => (
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
                    <p className="text-muted-foreground py-12 text-center">
                        No lists yet. Create one to start tracking your
                        to-dos.
                    </p>
                )}
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

TodoListsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'My To-Dos',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});

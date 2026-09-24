import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import TaskChecklist from '@/components/projects/task-checklist';
import TaskDeleteModal from '@/components/projects/task-delete-modal';
import TaskDependencyEditor from '@/components/projects/task-dependency-editor';
import TaskFormModal from '@/components/projects/task-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { show as showTask } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    PriorityOption,
    ProjectModule,
    SprintOption,
    Task,
    TaskDependencyTypeOption,
    TaskDetail,
    TaskLabel,
    TaskReference,
    TaskStatusOption,
    TaskTypeOption,
    TeamMemberOption,
    TodoList,
} from '@/types';

type Props = {
    project: { id: number; code: string; name: string };
    task: TaskDetail;
    taskTypes: TaskTypeOption[];
    modules: ProjectModule[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
    taskCandidates: TaskReference[];
    statusOptions: TaskStatusOption[];
    priorityOptions: PriorityOption[];
    dependencyTypeOptions: TaskDependencyTypeOption[];
    checklist: TodoList;
    projectMembers: TeamMemberOption[];
    canManageTask: boolean;
    canDeleteTask: boolean;
};

export default function TaskShow({
    project,
    task,
    taskTypes,
    modules,
    milestones,
    sprints,
    labels,
    taskCandidates,
    statusOptions,
    priorityOptions,
    dependencyTypeOptions,
    checklist,
    projectMembers,
    canManageTask,
    canDeleteTask,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [addingSubtask, setAddingSubtask] = useState(false);

    const reload = (only: string[]) => router.reload({ only });

    return (
        <>
            <Head title={`${task.reference} — ${task.title}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        {task.parent ? (
                            <Link
                                href={
                                    teamSlug
                                        ? showTask.url([
                                              teamSlug,
                                              project.id,
                                              task.parent.id,
                                          ])
                                        : '#'
                                }
                                className="text-muted-foreground hover:text-foreground text-sm"
                            >
                                ↑ {task.parent.reference} {task.parent.title}
                            </Link>
                        ) : null}
                        <Heading
                            title={`${task.reference} · ${task.title}`}
                            description={task.description ?? undefined}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        {canManageTask ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditOpen(true)}
                                data-test="task-edit-button"
                            >
                                <Pencil className="h-4 w-4" /> Edit
                            </Button>
                        ) : null}
                        {canDeleteTask ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteOpen(true)}
                                data-test="task-delete-button"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <Badge>{task.status.replace('_', ' ')}</Badge>
                            <p>{task.progress_percentage}% complete</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Type &amp; priority</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>{task.taskType.name}</p>
                            <p>{task.priority} priority</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Hours</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>Estimated: {task.estimated_hours ?? '—'}</p>
                            <p>Logged: {task.logged_hours}</p>
                            <p>Remaining: {task.remaining_hours ?? '—'}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Labels</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {task.labels.length > 0 ? (
                                <div className="flex flex-wrap gap-1">
                                    {task.labels.map((label) => (
                                        <Badge
                                            key={label.id}
                                            style={
                                                label.color
                                                    ? {
                                                          backgroundColor:
                                                              label.color,
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {label.name}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    No labels.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Assignees</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {task.assignees.length > 0 ? (
                                <p className="text-sm">
                                    {task.assignees
                                        .map((assignee) => assignee.name)
                                        .join(', ')}
                                </p>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    Unassigned.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Checklist</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TaskChecklist
                            checklist={checklist}
                            projectMembers={projectMembers}
                            taskTypes={taskTypes}
                            onChanged={() => reload(['checklist', 'task'])}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Subtasks</CardTitle>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setAddingSubtask(true)}
                                data-test="subtask-add"
                            >
                                <Plus className="h-4 w-4" /> Add subtask
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {task.subtasks.length > 0 ? (
                            <div className="space-y-2">
                                {task.subtasks.map((subtask: Task) => (
                                    <Link
                                        key={subtask.id}
                                        href={
                                            teamSlug
                                                ? showTask.url([
                                                      teamSlug,
                                                      project.id,
                                                      subtask.id,
                                                  ])
                                                : '#'
                                        }
                                        data-test="subtask-row"
                                        className="hover:bg-accent flex items-center justify-between gap-2 rounded-lg border p-2"
                                    >
                                        <span className="text-sm">
                                            <span className="text-muted-foreground font-mono">
                                                {subtask.reference}
                                            </span>{' '}
                                            {subtask.title}
                                        </span>
                                        <Badge variant="secondary">
                                            {subtask.status.replace('_', ' ')}
                                        </Badge>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                No subtasks yet.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Dependencies</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TaskDependencyEditor
                            projectId={project.id}
                            taskId={task.id}
                            dependencies={task.dependencies}
                            candidates={taskCandidates}
                            typeOptions={dependencyTypeOptions}
                            canManage={canManageTask}
                            onChanged={() => reload(['task', 'taskCandidates'])}
                        />
                    </CardContent>
                </Card>
            </div>

            <TaskFormModal
                open={editOpen}
                onOpenChange={setEditOpen}
                projectId={project.id}
                task={task}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={() => reload(['task', 'checklist'])}
            />

            <TaskFormModal
                open={addingSubtask}
                onOpenChange={setAddingSubtask}
                projectId={project.id}
                defaultParentId={task.id}
                taskTypes={taskTypes}
                modules={modules}
                milestones={milestones}
                sprints={sprints}
                labels={labels}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                onSaved={() => reload(['task'])}
            />

            <TaskDeleteModal
                projectId={project.id}
                task={task}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                onDeleted={() => {
                    if (teamSlug) {
                        router.visit(showProject.url([teamSlug, project.id]));
                    }
                }}
            />
        </>
    );
}

TaskShow.layout = (props: {
    project: { id: number; code: string; name: string };
    task: TaskDetail;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: props.currentTeam
                ? projectsIndex(props.currentTeam.slug)
                : '/',
        },
        {
            title: props.project.name,
            href: props.currentTeam
                ? showProject.url([props.currentTeam.slug, props.project.id])
                : '#',
        },
        {
            title: props.task.reference,
            href: '#',
        },
    ],
});

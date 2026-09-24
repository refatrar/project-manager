<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\ChangeTaskStatus;
use App\Actions\OMS\CreateTask;
use App\Actions\OMS\GetOrCreateTaskChecklist;
use App\Enums\Priority;
use App\Enums\TaskDependencyType;
use App\Enums\TaskStatus;
use App\Enums\TaskTypeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\MoveTaskRequest;
use App\Http\Requests\OMS\SaveTaskRequest;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use App\Models\Setup\Label;
use App\Models\Setup\TaskType;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Store a newly created task on the project.
     */
    public function store(SaveTaskRequest $request, Team $current_team, Project $project, CreateTask $createTask): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('create', [Task::class, $project]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $task = $createTask->handle($project, $user, $request->safe()->only([
            'title', 'description', 'task_type_id', 'project_module_id', 'milestone_id', 'sprint_id', 'status', 'parent_id',
            'priority', 'estimated_hours', 'remaining_hours', 'is_billable', 'starts_at', 'due_at',
        ]));
        $task->labels()->sync($request->validated('label_ids', []));
        $task->load(['taskType:id,name', 'assignees:id,name', 'assignments.user:id,name', 'labels:id,name,color']);

        return $this->savedResponse($request, $current_team, $project, $task, __('Task created.'), 201);
    }

    /**
     * Display the task detail page.
     */
    public function show(Team $current_team, Project $project, Task $task, GetOrCreateTaskChecklist $getOrCreateTaskChecklist): Response
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('view', $task);

        $checklist = $getOrCreateTaskChecklist->handle($task, $project);

        $task->load([
            'taskType:id,name',
            'assignees:id,name',
            'assignments.user:id,name',
            'labels:id,name,color',
            'parent:id,project_id,number,title,status',
            'subtasks' => fn ($query) => $query->with(['taskType:id,name', 'assignees:id,name', 'assignments.user:id,name', 'labels:id,name,color'])->orderBy('position'),
            'dependencies.relatedTask:id,project_id,number,title,status',
        ]);

        return Inertia::render('projects/tasks/show', [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
            ],
            'task' => [
                ...$task->toDetailArray(),
                'parent' => $task->parent ? [
                    'id' => $task->parent->id,
                    'reference' => $task->parent->reference(),
                    'title' => $task->parent->title,
                    'status' => $task->parent->status->value,
                ] : null,
                'subtasks' => $task->subtasks->map(fn (Task $subtask): array => $subtask->toBoardArray())->values(),
                'dependencies' => $task->dependencies->map(fn (TaskDependency $dependency): array => $dependency->toListArray())->values(),
            ],
            'taskTypes' => TaskType::query()->where('status', TaskTypeStatus::Active)->orderBy('name')->get(['id', 'name']),
            'modules' => $project->modules()->orderBy('position')->get()->map(fn (ProjectModule $module): array => $module->toListArray()),
            'labels' => Label::query()->where('team_id', $current_team->id)->orderBy('name')->get(['id', 'name', 'color']),
            'milestones' => $project->milestones()->orderBy('position')->get(['id', 'name']),
            'sprints' => $project->sprints()->orderBy('starts_on')->get(['id', 'name']),
            'taskCandidates' => $project->tasks()
                ->where('id', '!=', $task->id)
                ->orderBy('number')
                ->get(['id', 'number', 'title', 'status', 'project_id'])
                ->map(fn (Task $candidate): array => [
                    'id' => $candidate->id,
                    'reference' => $candidate->reference(),
                    'title' => $candidate->title,
                    'status' => $candidate->status->value,
                ]),
            'statusOptions' => TaskStatus::options(),
            'priorityOptions' => Priority::options(),
            'dependencyTypeOptions' => TaskDependencyType::options(),
            'checklist' => $checklist->toListArray(),
            'projectMembers' => $project->members()->active()->with('user:id,name,email')->get()
                ->map(fn (ProjectMember $member): array => [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                ]),
            'canManageTask' => Gate::allows('update', $task),
            'canDeleteTask' => Gate::allows('delete', $task),
        ]);
    }

    /**
     * Update the specified task's own fields (not its board position).
     */
    public function update(SaveTaskRequest $request, Team $current_team, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('update', $task);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $task->fill($request->safe()->only([
            'title', 'description', 'task_type_id', 'project_module_id', 'milestone_id', 'sprint_id',
            'priority', 'estimated_hours', 'remaining_hours', 'is_billable', 'starts_at', 'due_at',
        ]));
        $task->updated_by = $user->id;
        $task->save();
        $task->labels()->sync($request->validated('label_ids', []));
        $task->load(['taskType:id,name', 'assignees:id,name', 'assignments.user:id,name', 'labels:id,name,color']);

        return $this->savedResponse($request, $current_team, $project, $task, __('Task updated.'));
    }

    /**
     * Move the task to a new status and board position.
     */
    public function move(MoveTaskRequest $request, Team $current_team, Project $project, Task $task, ChangeTaskStatus $changeTaskStatus): JsonResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('changeStatus', $task);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $status = TaskStatus::from($request->validated('status'));
        $moved = $changeTaskStatus->handle($task, $status, (int) $request->validated('position'), $user);
        $moved->load(['taskType:id,name', 'assignees:id,name', 'assignments.user:id,name', 'labels:id,name,color']);

        return response()->json([
            'task' => $moved->toBoardArray(),
            'message' => __('Task moved.'),
        ]);
    }

    /**
     * Soft delete the specified task.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('delete', $task);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $task->deleted_by = $user->id;
        $task->save();
        $task->delete();

        return response()->json([
            'message' => __('Task deleted.'),
        ]);
    }

    /**
     * A project scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeProjectOnTeam(Team $current_team, Project $project): void
    {
        abort_unless($project->team_id === $current_team->id, 404);
    }

    /**
     * A task scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeTaskOnProject(Team $current_team, Project $project, Task $task): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($task->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, Task $task, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'task' => $task->toBoardArray(),
            'message' => $message,
        ], $status);
    }
}

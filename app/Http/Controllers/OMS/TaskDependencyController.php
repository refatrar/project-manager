<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\AddTaskDependency;
use App\Actions\OMS\RecordActivity;
use App\Enums\TaskDependencyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveTaskDependencyRequest;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class TaskDependencyController extends Controller
{
    /**
     * Declare a dependency from the task to another task in the project.
     */
    public function store(SaveTaskDependencyRequest $request, Team $current_team, Project $project, Task $task, AddTaskDependency $addTaskDependency): JsonResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('update', $task);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $relatedTask = Task::query()
            ->where('project_id', $project->id)
            ->where('id', $request->validated('related_task_id'))
            ->firstOrFail();

        try {
            $dependency = $addTaskDependency->handle(
                $task,
                $relatedTask,
                TaskDependencyType::from($request->validated('type')),
                $user,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $dependency->load('relatedTask:id,project_id,number,title,status');

        return response()->json([
            'dependency' => $dependency->toListArray(),
            'message' => __('Dependency added.'),
        ], 201);
    }

    /**
     * Remove the specified dependency.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task, TaskDependency $dependency, RecordActivity $recordActivity): JsonResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('update', $task);

        abort_unless($dependency->task_id === $task->id, 404);
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $dependency->load('relatedTask:id,project_id,number,title,status');
        $task->loadMissing('project.team');
        $type = $dependency->type;
        $relatedReference = $dependency->relatedTask->reference();

        $dependency->delete();

        $recordActivity->handle(
            team: $task->project->team,
            project: $task->project,
            userId: $user->id,
            event: 'dependency.removed',
            description: "\"{$task->reference()}\" is no longer {$type->label()} \"{$relatedReference}\"",
        );

        return response()->json([
            'message' => __('Dependency removed.'),
        ]);
    }

    /**
     * A task scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeTaskOnProject(Team $current_team, Project $project, Task $task): void
    {
        abort_unless($project->team_id === $current_team->id, 404);
        abort_unless($task->project_id === $project->id, 404);
    }
}

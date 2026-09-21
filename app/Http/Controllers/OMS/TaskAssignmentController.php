<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\AssignTask;
use App\Enums\TaskAssignmentRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\AssignTaskRequest;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class TaskAssignmentController extends Controller
{
    /**
     * Assign a user to the task in a given role.
     */
    public function store(AssignTaskRequest $request, Team $current_team, Project $project, Task $task, AssignTask $assignTask): JsonResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('update', $task);

        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $assignment = $assignTask->assign(
                $task,
                (int) $request->validated('user_id'),
                TaskAssignmentRole::from($request->validated('role')),
                $request->validated('allocated_hours'),
                $user,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $assignment->load('user:id,name');

        return response()->json([
            'assignment' => $assignment->toListArray(),
            'message' => __('Assignment added.'),
        ], 201);
    }

    /**
     * Remove a user from the task, preserving the assignment as history.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task, TaskAssignment $assignment, AssignTask $assignTask): JsonResponse
    {
        $this->authorizeTaskOnProject($current_team, $project, $task);
        Gate::authorize('update', $task);

        abort_unless($assignment->task_id === $task->id, 404);
        $user = $request->user();
        abort_unless($user !== null, 403);

        $assignTask->unassign($assignment, $user);

        return response()->json([
            'message' => __('Assignment removed.'),
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

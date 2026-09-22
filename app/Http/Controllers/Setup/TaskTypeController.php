<?php

namespace App\Http\Controllers\Setup;

use App\Enums\TaskTypeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SaveTaskTypeRequest;
use App\Models\Setup\TaskType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskTypeController extends Controller
{
    /**
     * Display a listing of the task types.
     */
    public function index(): Response
    {
        $taskTypes = TaskType::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(7)
            ->withQueryString()
            ->through(fn (TaskType $taskType): array => $taskType->toSetupArray());

        return Inertia::render('setup/task-types/index', [
            'taskTypes' => $taskTypes,
            'statusOptions' => TaskTypeStatus::options(),
        ]);
    }

    /**
     * Store a newly created task type.
     */
    public function store(SaveTaskTypeRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $taskType = new TaskType($request->safe()->only(['name', 'description', 'status']));
        $taskType->created_by = $user->id;
        $taskType->save();

        return $this->savedResponse($request, $taskType, __('Task type created.'), 201);
    }

    /**
     * Update the specified task type.
     */
    public function update(SaveTaskTypeRequest $request, string $current_team, TaskType $task_type): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $task_type->fill($request->safe()->only(['name', 'description', 'status']));
        $task_type->updated_by = $user->id;
        $task_type->save();

        return $this->savedResponse($request, $task_type, __('Task type updated.'));
    }

    /**
     * Soft delete the specified task type.
     */
    public function destroy(Request $request, string $current_team, TaskType $task_type): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $task_type->deleted_by = $user->id;
        $task_type->save();
        $task_type->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Task type deleted.')]);

            return to_route('setup.task-types.index');
        }

        return response()->json([
            'message' => __('Task type deleted.'),
        ]);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, TaskType $taskType, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('setup.task-types.index');
        }

        return response()->json([
            'taskType' => $taskType->toSetupArray(),
            'message' => $message,
        ], $status);
    }
}

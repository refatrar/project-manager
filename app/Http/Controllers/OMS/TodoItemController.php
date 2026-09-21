<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\PromoteTodoItemToTask;
use App\Actions\OMS\ToggleTodoItem;
use App\Enums\TodoListType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\PromoteTodoItemRequest;
use App\Http\Requests\OMS\SaveTodoItemRequest;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TodoItemController extends Controller
{
    /**
     * Add an item to the to-do list.
     */
    public function store(SaveTodoItemRequest $request, Team $current_team, TodoList $todo_list): JsonResponse|RedirectResponse
    {
        $this->authorizeListOnTeam($current_team, $todo_list);
        Gate::authorize('update', $todo_list);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $item = new TodoItem($request->safe()->only(['title', 'notes', 'priority', 'due_at', 'estimated_minutes', 'assigned_to']));
        $item->todo_list_id = $todo_list->id;
        $item->created_by = $user->id;
        $item->save();
        $item->load(['assignee:id,name', 'task.project:id,code']);

        return $this->savedResponse($request, $current_team, $todo_list, $item, __('Item added.'), 201);
    }

    /**
     * Update the specified item's details.
     */
    public function update(SaveTodoItemRequest $request, Team $current_team, TodoList $todo_list, TodoItem $item): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnList($current_team, $todo_list, $item);
        Gate::authorize('update', $todo_list);

        abort_unless($request->user() !== null, 403);

        $item->fill($request->safe()->only(['title', 'notes', 'priority', 'due_at', 'estimated_minutes', 'assigned_to']));
        $item->save();
        $item->load(['assignee:id,name', 'task.project:id,code']);

        return $this->savedResponse($request, $current_team, $todo_list, $item, __('Item updated.'));
    }

    /**
     * Mark the item done or not done, closing its linked task when done (RD.md FR-5.4).
     */
    public function toggle(Request $request, Team $current_team, TodoList $todo_list, TodoItem $item, ToggleTodoItem $toggleTodoItem): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnList($current_team, $todo_list, $item);
        Gate::authorize('update', $todo_list);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $item = $toggleTodoItem->handle($item, $request->boolean('is_completed'), $user);
        $item->load(['assignee:id,name', 'task.project:id,code']);

        return $this->savedResponse($request, $current_team, $todo_list, $item, __('Item updated.'));
    }

    /**
     * Turn the item into a real task. A task-checklist item is promoted
     * into a subtask of the task the checklist belongs to; every other
     * item is promoted into a standalone task on the chosen project.
     */
    public function promote(PromoteTodoItemRequest $request, Team $current_team, TodoList $todo_list, TodoItem $item, PromoteTodoItemToTask $promoteTodoItemToTask): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnList($current_team, $todo_list, $item);
        Gate::authorize('update', $todo_list);

        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($todo_list->type === TodoListType::TaskChecklist) {
            $project = Project::query()->where('team_id', $current_team->id)->whereKey($todo_list->project_id)->firstOrFail();
        } else {
            $project = Project::query()->where('team_id', $current_team->id)->whereKey($request->validated('project_id'))->firstOrFail();
        }

        Gate::authorize('create', [Task::class, $project]);

        $promoteTodoItemToTask->handle($item, $project, $user, [
            'task_type_id' => $request->validated('task_type_id'),
        ]);

        $item->load(['assignee:id,name', 'task.project:id,code']);

        return $this->savedResponse($request, $current_team, $todo_list, $item, __('Task created.'), 201);
    }

    /**
     * Remove the specified item from the list.
     */
    public function destroy(Request $request, Team $current_team, TodoList $todo_list, TodoItem $item): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnList($current_team, $todo_list, $item);
        Gate::authorize('update', $todo_list);

        abort_unless($request->user() !== null, 403);

        $item->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed.')]);

            return to_route('todo-lists.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('Item removed.'),
        ]);
    }

    /**
     * A list scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeListOnTeam(Team $current_team, TodoList $list): void
    {
        abort_unless($list->team_id === $current_team->id, 404);
    }

    /**
     * An item scoped by the URL's list and team, or a 404 (NFR-1).
     */
    private function authorizeItemOnList(Team $current_team, TodoList $list, TodoItem $item): void
    {
        $this->authorizeListOnTeam($current_team, $list);

        abort_unless($item->todo_list_id === $list->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, TodoList $list, TodoItem $item, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        $list->loadMissing(['items.assignee:id,name', 'items.task.project:id,code']);

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('todo-lists.index', ['current_team' => $team->slug]);
        }

        return response()->json([
            'item' => $item->toListArray(),
            'list' => $list->toListArray(),
            'message' => $message,
        ], $status);
    }
}

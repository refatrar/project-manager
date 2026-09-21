<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\GenerateDailyTodoList;
use App\Enums\Priority;
use App\Enums\TaskTypeStatus;
use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveTodoListRequest;
use App\Models\OMS\Project;
use App\Models\OMS\TodoList;
use App\Models\Setup\TaskType;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TodoListController extends Controller
{
    /**
     * Display the user's to-do lists on the team.
     */
    public function index(Request $request, Team $current_team, GenerateDailyTodoList $generateDailyTodoList): Response
    {
        Gate::authorize('viewAny', [TodoList::class, $current_team]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $generateDailyTodoList->handle($user, $current_team, Carbon::now());

        $lists = TodoList::query()
            ->where('team_id', $current_team->id)
            ->where('owner_id', $user->id)
            ->whereIn('type', [TodoListType::Custom, TodoListType::Daily, TodoListType::Generated])
            ->with(['items.assignee:id,name', 'items.task.project:id,code'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TodoList $list): array => $list->toListArray());

        $teamMembers = $current_team->members()->get(['users.id', 'users.name', 'users.email']);

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->forMember($user)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $taskTypes = TaskType::query()
            ->where('status', TaskTypeStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('todo-lists/index', [
            'lists' => $lists,
            'teamMembers' => $teamMembers,
            'projects' => $projects,
            'taskTypes' => $taskTypes,
            'typeOptions' => collect([TodoListType::Custom, TodoListType::Daily])
                ->map(fn (TodoListType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
            'statusOptions' => TodoListStatus::options(),
            'priorityOptions' => Priority::options(),
        ]);
    }

    /**
     * Store a newly created to-do list.
     */
    public function store(SaveTodoListRequest $request, Team $current_team): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [TodoList::class, $current_team]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $list = new TodoList($request->safe()->only(['name', 'description', 'type', 'status', 'scheduled_for']));
        $list->team_id = $current_team->id;
        $list->owner_id = $user->id;
        $list->created_by = $user->id;
        $list->save();
        $list->setRelation('items', $list->items()->get());

        return $this->savedResponse($request, $current_team, $list, __('List created.'), 201);
    }

    /**
     * Update the specified to-do list.
     */
    public function update(SaveTodoListRequest $request, Team $current_team, TodoList $todo_list): JsonResponse|RedirectResponse
    {
        $this->authorizeListOnTeam($current_team, $todo_list);
        Gate::authorize('update', $todo_list);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $todo_list->fill($request->safe()->only(['name', 'description', 'status', 'scheduled_for']));
        $todo_list->updated_by = $user->id;
        $todo_list->save();
        $todo_list->load(['items.assignee:id,name', 'items.task.project:id,code']);

        return $this->savedResponse($request, $current_team, $todo_list, __('List updated.'));
    }

    /**
     * Delete the specified to-do list.
     */
    public function destroy(Request $request, Team $current_team, TodoList $todo_list): JsonResponse|RedirectResponse
    {
        $this->authorizeListOnTeam($current_team, $todo_list);
        Gate::authorize('delete', $todo_list);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $todo_list->deleted_by = $user->id;
        $todo_list->save();
        $todo_list->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('List deleted.')]);

            return to_route('todo-lists.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('List deleted.'),
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
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, TodoList $list, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('todo-lists.index', ['current_team' => $team->slug]);
        }

        return response()->json([
            'list' => $list->toListArray(),
            'message' => $message,
        ], $status);
    }
}

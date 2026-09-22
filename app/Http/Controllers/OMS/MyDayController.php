<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\GenerateDailyTodoList;
use App\Enums\Priority;
use App\Enums\TaskTypeStatus;
use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Http\Controllers\Controller;
use App\Models\OMS\Project;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Setup\TaskType;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MyDayController extends Controller
{
    /**
     * Display the user's day: their own to-do lists, plus every open
     * task-checklist item assigned to them across every project (FR-5's
     * "My day" combined view). Meeting action items belong here too
     * (RD.md FR-6.5) but are genuinely blocked — Phase 4 (Meetings) has
     * no controller yet, so there is nothing to query.
     */
    public function index(Request $request, Team $current_team, GenerateDailyTodoList $generateDailyTodoList): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $generateDailyTodoList->handle($user, $current_team, Carbon::now());

        $todoLists = TodoList::query()
            ->where('team_id', $current_team->id)
            ->where('owner_id', $user->id)
            ->whereIn('type', [TodoListType::Custom, TodoListType::Daily, TodoListType::Generated])
            ->with(['items.assignee:id,name', 'items.task.project:id,code'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TodoList $list): array => $list->toListArray());

        $checklistItems = TodoItem::query()
            ->where('assigned_to', $user->id)
            ->where('is_completed', false)
            ->whereHas('list', fn ($query) => $query
                ->where('team_id', $current_team->id)
                ->where('type', TodoListType::TaskChecklist->value))
            ->with(['assignee:id,name', 'task.project:id,code'])
            ->orderByRaw('due_at is null, due_at')
            ->limit(50)
            ->get()
            ->map(fn (TodoItem $item): array => $item->toListArray());

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

        return Inertia::render('my-day/index', [
            'todoLists' => $todoLists,
            'checklistItems' => $checklistItems,
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
}

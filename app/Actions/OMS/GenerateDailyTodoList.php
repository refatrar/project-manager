<?php

namespace App\Actions\OMS;

use App\Enums\TaskStatus;
use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Task;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

class GenerateDailyTodoList
{
    /**
     * Build the user's generated list of open tasks due within the next
     * week, for the team's current work (FR-5.6). Idempotent per
     * (user, team, date): calling this again for a date that already has
     * a generated list just returns it, rather than rebuilding it — a
     * generated list is a snapshot taken once, not kept continuously in
     * sync with tasks that change afterward.
     */
    public function handle(User $user, Team $team, Carbon $date): TodoList
    {
        $existing = TodoList::query()
            ->where('owner_id', $user->id)
            ->where('team_id', $team->id)
            ->where('type', TodoListType::Generated->value)
            ->whereDate('scheduled_for', $date)
            ->first();

        if ($existing !== null) {
            $existing->loadMissing('items.assignee:id,name');

            return $existing;
        }

        $list = new TodoList([
            'name' => 'Due this week',
            'type' => TodoListType::Generated,
            'status' => TodoListStatus::Open,
            'scheduled_for' => $date,
        ]);
        $list->team_id = $team->id;
        $list->owner_id = $user->id;
        $list->created_by = $user->id;
        $list->save();

        $dueBefore = $date->copy()->addDays(7)->endOfDay();

        $tasks = Task::query()
            ->whereHas('project', fn ($query) => $query->where('team_id', $team->id))
            ->whereHas('assignments', fn ($query) => $query->active()->where('user_id', $user->id))
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', $dueBefore))
            ->orderByRaw('due_at is null, due_at')
            ->get();

        foreach ($tasks as $position => $task) {
            $list->items()->create([
                'task_id' => $task->id,
                'title' => "{$task->reference()} {$task->title}",
                'priority' => $task->priority,
                'due_at' => $task->due_at,
                'position' => $position,
            ]);
        }

        $list->loadMissing('items.assignee:id,name');

        return $list;
    }
}

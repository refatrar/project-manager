<?php

namespace App\Actions\OMS;

use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncTaskChecklistItems
{
    public function __construct(
        private readonly GetOrCreateTaskChecklist $getOrCreateTaskChecklist,
    ) {
        //
    }

    /**
     * Make the task's checklist hold exactly the given to-dos, in order.
     * Rows carrying an `id` rename that existing item (keeping its
     * completion, assignee and linked task); rows without one are added;
     * existing items missing from the payload are removed. No checklist is
     * created just to hold an empty list.
     *
     * @param  array<int, array{id?: int|null, title: string}>  $todos
     */
    public function handle(Task $task, Project $project, User $actingUser, array $todos): void
    {
        $hasChecklist = TodoList::query()
            ->where('task_id', $task->id)
            ->where('type', TodoListType::TaskChecklist->value)
            ->exists();

        if ($todos === [] && ! $hasChecklist) {
            return;
        }

        DB::transaction(function () use ($task, $project, $actingUser, $todos): void {
            $checklist = $this->getOrCreateTaskChecklist->handle($task, $project);
            $existing = $checklist->items->keyBy('id');
            $keptIds = [];

            foreach (array_values($todos) as $position => $todo) {
                $item = isset($todo['id']) ? $existing->get((int) $todo['id']) : null;

                if ($item === null) {
                    $item = new TodoItem;
                    $item->todo_list_id = $checklist->id;
                    $item->created_by = $actingUser->id;
                }

                $item->title = $todo['title'];
                $item->position = $position;
                $item->save();

                $keptIds[] = $item->id;
            }

            $checklist->items()->whereNotIn('id', $keptIds)->delete();
        });
    }
}

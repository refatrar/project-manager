<?php

namespace App\Actions\OMS;

use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoList;

class GetOrCreateTaskChecklist
{
    /**
     * Get the task's checklist, creating it the first time an item is
     * needed. A task has at most one checklist — the pair is unique by
     * (task_id, type) in practice, enforced here rather than at the
     * database level (DESIGN.md's four nullable context keys).
     */
    public function handle(Task $task, Project $project): TodoList
    {
        $list = TodoList::query()
            ->where('task_id', $task->id)
            ->where('type', TodoListType::TaskChecklist->value)
            ->first();

        if ($list === null) {
            $list = new TodoList([
                'project_id' => $project->id,
                'task_id' => $task->id,
                'name' => 'Checklist',
                'type' => TodoListType::TaskChecklist,
                'status' => TodoListStatus::Open,
            ]);
            $list->team_id = $project->team_id;
            $list->save();
        }

        $list->loadMissing(['items.assignee:id,name', 'items.task.project:id,code']);

        return $list;
    }
}

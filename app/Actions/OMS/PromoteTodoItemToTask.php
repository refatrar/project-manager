<?php

namespace App\Actions\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\User;

class PromoteTodoItemToTask
{
    public function __construct(
        private readonly CreateTask $createTask,
        private readonly AssignTask $assignTask,
    ) {
        //
    }

    /**
     * Turn a to-do item into a real task, keeping them linked (RD.md
     * FR-5.4) so `ToggleTodoItem` can close the task later. When the item
     * is a task-checklist line (its list's `task_id` is set), the new
     * task becomes a subtask of that task instead of a standalone one —
     * the "reverse" direction: a checklist step substantial enough to
     * need its own tracking, rather than a personal item graduating into
     * project work.
     *
     * @param  array<string, mixed>  $attributes  must include `task_type_id`
     */
    public function handle(TodoItem $item, Project $project, User $user, array $attributes): Task
    {
        $item->loadMissing('list');

        $parentId = $item->list->type === TodoListType::TaskChecklist
            ? $item->list->task_id
            : null;

        $task = $this->createTask->handle($project, $user, [
            ...$attributes,
            'title' => $item->title,
            'description' => $item->notes,
            'due_at' => $item->due_at,
            'priority' => $item->priority,
            'parent_id' => $parentId,
        ]);

        $item->task_id = $task->id;
        $item->save();

        if ($item->assigned_to !== null) {
            $isProjectMember = $project->members()->active()->where('user_id', $item->assigned_to)->exists();

            if ($isProjectMember) {
                $this->assignTask->assign($task, $item->assigned_to, TaskAssignmentRole::Assignee, null, $user);
            }
        }

        return $task;
    }
}

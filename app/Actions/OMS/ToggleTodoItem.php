<?php

namespace App\Actions\OMS;

use App\Enums\TaskStatus;
use App\Models\OMS\TodoItem;
use App\Models\User;
use Illuminate\Support\Carbon;

class ToggleTodoItem
{
    public function __construct(
        private readonly ChangeTaskStatus $changeTaskStatus,
    ) {
        //
    }

    /**
     * Mark a to-do item done or not done. Completing an item linked to a
     * real task also closes that task, so ticking it off and closing the
     * task stay connected (RD.md FR-5.4). Un-completing an item never
     * reopens its task — that direction is not implied by the requirement,
     * and reopening a task someone else has since moved forward on would
     * be surprising.
     */
    public function handle(TodoItem $item, bool $completed, User $actingUser): TodoItem
    {
        $item->is_completed = $completed;
        $item->completed_by = $completed ? $actingUser->id : null;
        $item->completed_at = $completed ? Carbon::now() : null;
        $item->save();

        if ($completed && $item->task_id !== null) {
            $item->loadMissing('task');

            if ($item->task !== null && $item->task->status !== TaskStatus::Done) {
                $this->changeTaskStatus->handle($item->task, TaskStatus::Done, $item->task->position, $actingUser);
            }
        }

        return $item->refresh();
    }
}

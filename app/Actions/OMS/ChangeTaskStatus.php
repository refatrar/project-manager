<?php

namespace App\Actions\OMS;

use App\Enums\TaskStatus;
use App\Models\OMS\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChangeTaskStatus
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
    ) {
        //
    }

    /**
     * Move a task to a new status and board position, recording the
     * transition in `task_status_histories` when the status actually
     * changes, and stamping `started_at` / `completed_at` (RD.md FR-2.4).
     */
    public function handle(Task $task, TaskStatus $newStatus, int $newPosition, User $changedBy): Task
    {
        DB::transaction(function () use ($task, $newStatus, $newPosition, $changedBy): void {
            $previousStatus = $task->status;

            if ($previousStatus !== $newStatus) {
                $now = Carbon::now();
                $lastHistory = $task->statusHistories()->latest('changed_at')->first();
                $spentSince = $lastHistory !== null ? $lastHistory->changed_at : $task->created_at;

                $task->statusHistories()->create([
                    'from_status' => $previousStatus->value,
                    'to_status' => $newStatus->value,
                    'changed_by' => $changedBy->id,
                    'changed_at' => $now,
                    'duration_minutes' => $spentSince !== null
                        ? (int) round($spentSince->diffInMinutes($now))
                        : null,
                ]);

                if ($newStatus === TaskStatus::InProgress && $task->started_at === null) {
                    $task->started_at = $now;
                }

                if ($newStatus === TaskStatus::Done && $task->completed_at === null) {
                    $task->completed_at = $now;
                }

                $task->loadMissing('project.team');
                $this->recordActivity->handle(
                    team: $task->project->team,
                    project: $task->project,
                    userId: $changedBy->id,
                    event: 'task.status_changed',
                    description: "Task \"{$task->reference()}\" moved from {$previousStatus->label()} to {$newStatus->label()}",
                    subject: $task,
                );
            }

            $task->status = $newStatus;
            $task->position = $newPosition;
            $task->updated_by = $changedBy->id;
            $task->save();
        });

        return $task->refresh();
    }
}

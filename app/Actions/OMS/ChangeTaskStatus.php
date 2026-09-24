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
            $task->position = $this->placeInColumn($task, $newStatus, $newPosition);
            $task->updated_by = $changedBy->id;
            $task->save();
        });

        return $task->refresh();
    }

    /**
     * Treat `$slot` as the task's index within the target column: the other
     * tasks there are renumbered 0..n around it, so two cards never share a
     * position and moving up/down always swaps with the neighbour. Returns
     * the task's own new position.
     */
    private function placeInColumn(Task $task, TaskStatus $status, int $slot): int
    {
        $siblings = Task::query()
            ->where('project_id', $task->project_id)
            ->where('status', $status->value)
            ->whereKeyNot($task->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'position']);

        $slot = max(0, min($slot, $siblings->count()));

        foreach ($siblings->values() as $index => $sibling) {
            $position = $index < $slot ? $index : $index + 1;

            if ($sibling->position !== $position) {
                // A plain column update: renumbering neighbours is not an
                // edit of those tasks, so no audit/updated_by churn.
                Task::query()->whereKey($sibling->id)->update(['position' => $position]);
            }
        }

        return $slot;
    }
}

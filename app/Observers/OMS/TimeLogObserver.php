<?php

namespace App\Observers\OMS;

use App\Actions\OMS\ReconcileTaskLoggedHours;
use App\Models\OMS\Task;
use App\Models\OMS\TimeLog;

/**
 * Keeps `tasks.logged_hours` correct as `time_logs` rows are written,
 * rather than only nightly (`oms:reconcile`). Reuses
 * `ReconcileTaskLoggedHours` — the same "one writer" logic, just called
 * more often — instead of a second, competing computation.
 */
class TimeLogObserver
{
    public function __construct(
        private readonly ReconcileTaskLoggedHours $reconcileTaskLoggedHours,
    ) {
        //
    }

    public function created(TimeLog $timeLog): void
    {
        $this->recalculate($timeLog->task_id);
    }

    /**
     * Recalculates both the old and new task when an entry is reassigned
     * to a different task, not just wherever it currently points.
     */
    public function updated(TimeLog $timeLog): void
    {
        if ($timeLog->wasChanged('task_id')) {
            $this->recalculate($timeLog->getOriginal('task_id'));
        }

        $this->recalculate($timeLog->task_id);
    }

    public function deleted(TimeLog $timeLog): void
    {
        $this->recalculate($timeLog->task_id);
    }

    public function restored(TimeLog $timeLog): void
    {
        $this->recalculate($timeLog->task_id);
    }

    private function recalculate(mixed $taskId): void
    {
        if (! is_int($taskId)) {
            return;
        }

        $task = Task::query()->find($taskId);

        if ($task !== null) {
            $this->reconcileTaskLoggedHours->handle($task);
        }
    }
}

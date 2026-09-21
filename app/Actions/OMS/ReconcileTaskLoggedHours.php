<?php

namespace App\Actions\OMS;

use App\Enums\ApprovalStatus;
use App\Models\OMS\Task;
use App\Models\OMS\TimeLog;

class ReconcileTaskLoggedHours
{
    /**
     * Recompute `tasks.logged_hours` from `time_logs`, the one authoritative
     * source of actual effort (DESIGN.md 299). A rejected or cancelled entry
     * was never real effort, so it does not count. This is the "one writer"
     * DESIGN.md calls for — nothing else may assign `logged_hours` directly.
     */
    public function handle(Task $task): void
    {
        $minutes = TimeLog::query()
            ->where('task_id', $task->id)
            ->whereNotIn('approval_status', [ApprovalStatus::Rejected->value, ApprovalStatus::Cancelled->value])
            ->sum('duration_minutes');

        $task->logged_hours = (string) round($minutes / 60, 2);
        $task->save();
    }
}

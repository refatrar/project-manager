<?php

namespace App\Actions\OMS;

use App\Data\CycleTimeReportData;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskStatusHistory;

class CalculateCycleTimeReport
{
    /**
     * Build the project's cycle-time and lead-time report from the
     * `tasks` timestamps and `task_status_histories.duration_minutes`
     * (RD.md FR-2.4). Lead time is creation to completion; cycle time is
     * first "in progress" to completion. Only tasks that have actually
     * completed (`completed_at` set) count toward either average.
     */
    public function handle(Project $project): CycleTimeReportData
    {
        $completed = Task::query()
            ->where('project_id', $project->id)
            ->whereNotNull('completed_at')
            ->get(['created_at', 'started_at', 'completed_at']);

        $leadTimes = $completed->map(
            fn (Task $task): float => $task->created_at->diffInMinutes($task->completed_at),
        );

        $cycleTimes = $completed
            ->filter(fn (Task $task): bool => $task->started_at !== null)
            ->map(fn (Task $task): float => $task->started_at->diffInMinutes($task->completed_at));

        $statusBreakdown = TaskStatusHistory::query()
            ->whereHas('task', fn ($query) => $query->where('project_id', $project->id))
            ->whereNotNull('duration_minutes')
            ->selectRaw('from_status, avg(duration_minutes) as avg_minutes, count(*) as transitions')
            ->groupBy('from_status')
            ->get()
            ->map(fn (TaskStatusHistory $row): array => [
                'status' => $row->from_status->value,
                'label' => $row->from_status->label(),
                'avgMinutes' => (float) $row->getAttribute('avg_minutes'),
                'transitions' => (int) $row->getAttribute('transitions'),
            ])
            ->sortByDesc('avgMinutes')
            ->values()
            ->all();

        return new CycleTimeReportData(
            avgLeadTimeMinutes: $leadTimes->isNotEmpty() ? (float) $leadTimes->avg() : null,
            avgCycleTimeMinutes: $cycleTimes->isNotEmpty() ? (float) $cycleTimes->avg() : null,
            completedTaskCount: $completed->count(),
            statusBreakdown: $statusBreakdown,
        );
    }
}

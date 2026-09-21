<?php

namespace App\Actions\OMS;

use App\Data\ProjectProgressData;
use App\Enums\TaskStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CalculateProjectProgress
{
    /**
     * Calculate progress across every task in the project.
     */
    public function handle(Project $project): ProjectProgressData
    {
        return $this->calculate($project->tasks());
    }

    /**
     * Calculate the progress percentage for a single module, from the
     * tasks directly assigned to it. Does not recurse into child modules.
     */
    public function forModule(ProjectModule $module): int
    {
        return $this->calculate($module->tasks())->progressPercentage;
    }

    /**
     * Calculate the progress percentage for a single milestone, from the
     * tasks assigned to it.
     */
    public function forMilestone(Milestone $milestone): int
    {
        return $this->calculate($milestone->tasks())->progressPercentage;
    }

    /**
     * @param  HasMany<Task, *>  $query
     */
    private function calculate(HasMany $query): ProjectProgressData
    {
        $tasks = $query->get(['status', 'due_at', 'estimated_hours', 'logged_hours', 'remaining_hours']);

        $total = $tasks->count();
        $completed = $tasks->where('status', TaskStatus::Done)->count();
        $inProgress = $tasks->filter(fn (Task $task): bool => $task->status->isActiveWork())->count();
        $blocked = $tasks->where('status', TaskStatus::Blocked)->count();

        $now = Carbon::now();
        $overdue = $tasks
            ->filter(fn (Task $task): bool => $task->due_at !== null && $task->due_at->lt($now) && ! $task->status->isClosed())
            ->count();

        $relevant = $tasks->filter(fn (Task $task): bool => $task->status !== TaskStatus::Cancelled)->count();
        $progressPercentage = $relevant > 0 ? (int) round($completed / $relevant * 100) : 0;

        return new ProjectProgressData(
            totalTasks: $total,
            completedTasks: $completed,
            inProgressTasks: $inProgress,
            blockedTasks: $blocked,
            overdueTasks: $overdue,
            estimatedHours: (string) $tasks->sum('estimated_hours'),
            loggedHours: (string) $tasks->sum('logged_hours'),
            remainingHours: (string) $tasks->sum('remaining_hours'),
            progressPercentage: $progressPercentage,
        );
    }
}

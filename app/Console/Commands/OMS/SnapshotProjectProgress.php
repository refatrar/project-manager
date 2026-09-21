<?php

namespace App\Console\Commands\OMS;

use App\Actions\OMS\CalculateProjectProgress;
use App\Actions\OMS\DetermineProjectHealth;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotProjectProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oms:snapshot-project-progress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll up task completion to every project, module and milestone, and record a daily progress snapshot per project';

    /**
     * Execute the console command.
     */
    public function handle(CalculateProjectProgress $calculateProgress, DetermineProjectHealth $determineHealth): int
    {
        $today = Carbon::now()->toDateString();
        $snapshotted = 0;

        Project::query()
            ->open()
            ->chunkById(50, function ($projects) use ($calculateProgress, $determineHealth, $today, &$snapshotted): void {
                foreach ($projects as $project) {
                    $progress = $calculateProgress->handle($project);
                    $health = $determineHealth->handle($project, $progress);

                    $project->progress_percentage = $progress->progressPercentage;
                    $project->health = $health;
                    $project->save();

                    $this->snapshotModules($project, $calculateProgress);
                    $this->snapshotMilestones($project, $calculateProgress);

                    $project->progressSnapshots()->updateOrCreate(
                        ['sprint_id' => null, 'snapshot_on' => $today],
                        [
                            'total_tasks' => $progress->totalTasks,
                            'completed_tasks' => $progress->completedTasks,
                            'in_progress_tasks' => $progress->inProgressTasks,
                            'blocked_tasks' => $progress->blockedTasks,
                            'overdue_tasks' => $progress->overdueTasks,
                            'estimated_hours' => $progress->estimatedHours,
                            'logged_hours' => $progress->loggedHours,
                            'remaining_hours' => $progress->remainingHours,
                            'progress_percentage' => $progress->progressPercentage,
                            'health' => $health,
                        ],
                    );

                    $snapshotted++;
                }
            });

        $this->info("Snapshotted progress for {$snapshotted} project(s).");

        return self::SUCCESS;
    }

    /**
     * Roll task completion up to every module in the project.
     */
    private function snapshotModules(Project $project, CalculateProjectProgress $calculateProgress): void
    {
        $project->modules()->get(['id'])->each(function (ProjectModule $module) use ($calculateProgress): void {
            $module->progress_percentage = $calculateProgress->forModule($module);
            $module->save();
        });
    }

    /**
     * Roll task completion up to every milestone in the project.
     */
    private function snapshotMilestones(Project $project, CalculateProjectProgress $calculateProgress): void
    {
        $project->milestones()->get(['id'])->each(function (Milestone $milestone) use ($calculateProgress): void {
            $milestone->progress_percentage = $calculateProgress->forMilestone($milestone);
            $milestone->save();
        });
    }
}

<?php

namespace App\Console\Commands\OMS;

use App\Actions\OMS\CalculateProjectProgress;
use App\Actions\OMS\DetermineProjectHealth;
use App\Actions\OMS\ReconcileTaskLoggedHours;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use Illuminate\Console\Command;

class ReconcileProjectData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oms:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute tasks.logged_hours from time_logs, and progress percentages and health for every project, correcting any drift (DESIGN.md 299)';

    /**
     * Execute the console command.
     *
     * Unlike `oms:snapshot-project-progress`, this covers every project —
     * including archived ones, whose cached percentages can still drift —
     * and never writes a `project_progress_snapshots` row: it repairs the
     * live columns, it does not record history.
     */
    public function handle(
        ReconcileTaskLoggedHours $reconcileHours,
        CalculateProjectProgress $calculateProgress,
        DetermineProjectHealth $determineHealth,
    ): int {
        $tasksReconciled = 0;

        Task::query()->chunkById(200, function ($tasks) use ($reconcileHours, &$tasksReconciled): void {
            foreach ($tasks as $task) {
                $reconcileHours->handle($task);
                $tasksReconciled++;
            }
        });

        $projectsReconciled = 0;

        Project::query()->chunkById(50, function ($projects) use ($calculateProgress, $determineHealth, &$projectsReconciled): void {
            foreach ($projects as $project) {
                $progress = $calculateProgress->handle($project);
                $health = $determineHealth->handle($project, $progress);

                $project->progress_percentage = $progress->progressPercentage;
                $project->health = $health;
                $project->save();

                $this->reconcileModules($project, $calculateProgress);
                $this->reconcileMilestones($project, $calculateProgress);

                $projectsReconciled++;
            }
        });

        $this->info("Reconciled logged hours for {$tasksReconciled} task(s) and progress for {$projectsReconciled} project(s).");

        return self::SUCCESS;
    }

    /**
     * Roll task completion up to every module in the project.
     */
    private function reconcileModules(Project $project, CalculateProjectProgress $calculateProgress): void
    {
        $project->modules()->get(['id'])->each(function (ProjectModule $module) use ($calculateProgress): void {
            $module->progress_percentage = $calculateProgress->forModule($module);
            $module->save();
        });
    }

    /**
     * Roll task completion up to every milestone in the project.
     */
    private function reconcileMilestones(Project $project, CalculateProjectProgress $calculateProgress): void
    {
        $project->milestones()->get(['id'])->each(function (Milestone $milestone) use ($calculateProgress): void {
            $milestone->progress_percentage = $calculateProgress->forMilestone($milestone);
            $milestone->save();
        });
    }
}

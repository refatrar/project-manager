<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\CalculateProjectProgress;
use App\Enums\TaskStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateProjectProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_with_no_tasks_has_zero_progress(): void
    {
        $project = Project::factory()->create();

        $progress = app(CalculateProjectProgress::class)->handle($project);

        $this->assertSame(0, $progress->totalTasks);
        $this->assertSame(0, $progress->progressPercentage);
    }

    public function test_progress_percentage_excludes_cancelled_tasks_from_the_denominator(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->count(2)->create(['status' => TaskStatus::Done]);
        Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);
        Task::factory()->for($project)->create(['status' => TaskStatus::Cancelled]);

        $progress = app(CalculateProjectProgress::class)->handle($project);

        // 2 done out of 3 relevant (4 total minus 1 cancelled) = 67%.
        $this->assertSame(4, $progress->totalTasks);
        $this->assertSame(2, $progress->completedTasks);
        $this->assertSame(67, $progress->progressPercentage);
    }

    public function test_overdue_only_counts_open_tasks_past_their_due_date(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create([
            'status' => TaskStatus::InProgress,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->for($project)->create([
            'status' => TaskStatus::Done,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->for($project)->create([
            'status' => TaskStatus::InProgress,
            'due_at' => now()->addDay(),
        ]);

        $progress = app(CalculateProjectProgress::class)->handle($project);

        $this->assertSame(1, $progress->overdueTasks);
    }

    public function test_hours_are_summed_across_the_projects_tasks(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create(['estimated_hours' => 5, 'logged_hours' => 2, 'remaining_hours' => 3]);
        Task::factory()->for($project)->create(['estimated_hours' => 10, 'logged_hours' => 4, 'remaining_hours' => 6]);

        $progress = app(CalculateProjectProgress::class)->handle($project);

        $this->assertSame('15', $progress->estimatedHours);
        $this->assertSame('6', $progress->loggedHours);
        $this->assertSame('9', $progress->remainingHours);
    }

    public function test_for_module_only_counts_tasks_directly_assigned_to_it(): void
    {
        $project = Project::factory()->create();
        $module = ProjectModule::factory()->for($project)->create();
        $otherModule = ProjectModule::factory()->for($project)->create();
        Task::factory()->for($project)->create(['project_module_id' => $module->id, 'status' => TaskStatus::Done]);
        Task::factory()->for($project)->create(['project_module_id' => $otherModule->id, 'status' => TaskStatus::Backlog]);
        Task::factory()->for($project)->create(['project_module_id' => null, 'status' => TaskStatus::Backlog]);

        $percentage = app(CalculateProjectProgress::class)->forModule($module);

        $this->assertSame(100, $percentage);
    }

    public function test_for_milestone_only_counts_tasks_assigned_to_it(): void
    {
        $project = Project::factory()->create();
        $milestone = Milestone::factory()->for($project)->create();
        Task::factory()->for($project)->create(['milestone_id' => $milestone->id, 'status' => TaskStatus::Done]);
        Task::factory()->for($project)->create(['milestone_id' => $milestone->id, 'status' => TaskStatus::Backlog]);
        Task::factory()->for($project)->create(['milestone_id' => null, 'status' => TaskStatus::Done]);

        $percentage = app(CalculateProjectProgress::class)->forMilestone($milestone);

        $this->assertSame(50, $percentage);
    }
}

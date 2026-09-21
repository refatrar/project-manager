<?php

namespace Tests\Feature\OMS;

use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotProjectProgressCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_project_module_and_milestone_progress_and_writes_a_snapshot(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Active, 'end_date' => null]);
        $module = ProjectModule::factory()->for($project)->create();
        $milestone = Milestone::factory()->for($project)->create();
        Task::factory()->for($project)->create([
            'project_module_id' => $module->id,
            'milestone_id' => $milestone->id,
            'status' => TaskStatus::Done,
        ]);
        Task::factory()->for($project)->create(['status' => TaskStatus::Backlog]);

        $this->artisan('oms:snapshot-project-progress')->assertSuccessful();

        $project->refresh();
        $module->refresh();
        $milestone->refresh();

        $this->assertSame(50, $project->progress_percentage);
        $this->assertSame(ProjectHealth::OnTrack, $project->health);
        $this->assertSame(100, $module->progress_percentage);
        $this->assertSame(100, $milestone->progress_percentage);

        $this->assertDatabaseHas('project_progress_snapshots', [
            'project_id' => $project->id,
            'sprint_id' => null,
            'snapshot_on' => now()->toDateString(),
            'total_tasks' => 2,
            'completed_tasks' => 1,
            'progress_percentage' => 50,
        ]);
    }

    public function test_running_it_twice_on_the_same_day_updates_rather_than_duplicates_the_snapshot(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Active]);
        Task::factory()->for($project)->create(['status' => TaskStatus::Done]);

        $this->artisan('oms:snapshot-project-progress')->assertSuccessful();
        Task::factory()->for($project)->create(['status' => TaskStatus::Backlog]);
        $this->artisan('oms:snapshot-project-progress')->assertSuccessful();

        $this->assertDatabaseCount('project_progress_snapshots', 1);
        $this->assertDatabaseHas('project_progress_snapshots', [
            'project_id' => $project->id,
            'total_tasks' => 2,
        ]);
    }

    public function test_archived_projects_are_not_snapshotted(): void
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::Active,
            'archived_at' => now(),
        ]);

        $this->artisan('oms:snapshot-project-progress')->assertSuccessful();

        $this->assertDatabaseCount('project_progress_snapshots', 0);
    }
}

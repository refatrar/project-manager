<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\ReconcileTaskLoggedHours;
use App\Enums\ApprovalStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use App\Models\OMS\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileProjectDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_hours_is_summed_from_time_logs_in_hours(): void
    {
        $task = Task::factory()->create(['logged_hours' => 0]);
        TimeLog::factory()->for($task)->create(['duration_minutes' => 90]);
        TimeLog::factory()->for($task)->create(['duration_minutes' => 30]);

        app(ReconcileTaskLoggedHours::class)->handle($task);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'logged_hours' => 2.00,
        ]);
    }

    public function test_rejected_and_cancelled_entries_do_not_count(): void
    {
        $task = Task::factory()->create(['logged_hours' => 0]);
        TimeLog::factory()->for($task)->create([
            'duration_minutes' => 60,
            'approval_status' => ApprovalStatus::Approved,
        ]);
        TimeLog::factory()->for($task)->create([
            'duration_minutes' => 999,
            'approval_status' => ApprovalStatus::Rejected,
        ]);
        TimeLog::factory()->for($task)->create([
            'duration_minutes' => 999,
            'approval_status' => ApprovalStatus::Cancelled,
        ]);

        app(ReconcileTaskLoggedHours::class)->handle($task);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'logged_hours' => 1.00,
        ]);
    }

    public function test_a_task_with_no_time_logs_reconciles_to_zero(): void
    {
        $task = Task::factory()->create(['logged_hours' => 12.5]);

        app(ReconcileTaskLoggedHours::class)->handle($task);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'logged_hours' => 0,
        ]);
    }

    public function test_the_command_reconciles_every_task_and_project_including_archived_ones(): void
    {
        $openProject = Project::factory()->create(['status' => ProjectStatus::Active]);
        $archivedProject = Project::factory()->create([
            'status' => ProjectStatus::Archived,
            'archived_at' => now(),
            'progress_percentage' => 0,
        ]);

        $openTask = Task::factory()->for($openProject)->create(['status' => TaskStatus::Done, 'logged_hours' => 0]);
        TimeLog::factory()->for($openTask)->create(['duration_minutes' => 120]);

        Task::factory()->for($archivedProject)->create(['status' => TaskStatus::Done]);
        $module = ProjectModule::factory()->for($archivedProject)->create(['progress_percentage' => 0]);
        Task::factory()->for($archivedProject)->create([
            'project_module_id' => $module->id,
            'status' => TaskStatus::Done,
        ]);
        $milestone = Milestone::factory()->for($archivedProject)->create(['progress_percentage' => 0]);
        Task::factory()->for($archivedProject)->create([
            'milestone_id' => $milestone->id,
            'status' => TaskStatus::Done,
        ]);

        $this->artisan('oms:reconcile')->assertSuccessful();

        $this->assertDatabaseHas('tasks', ['id' => $openTask->id, 'logged_hours' => 2.00]);
        $this->assertDatabaseHas('projects', ['id' => $archivedProject->id, 'progress_percentage' => 100]);
        $this->assertDatabaseHas('project_modules', ['id' => $module->id, 'progress_percentage' => 100]);
        $this->assertDatabaseHas('milestones', ['id' => $milestone->id, 'progress_percentage' => 100]);
    }

    public function test_the_command_does_not_write_a_progress_snapshot_row(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create(['status' => TaskStatus::Done]);

        $this->artisan('oms:reconcile')->assertSuccessful();

        $this->assertDatabaseCount('project_progress_snapshots', 0);
    }
}

<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\CalculateCycleTimeReport;
use App\Actions\OMS\ChangeTaskStatus;
use App\Enums\TaskStatus;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalculateCycleTimeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_with_no_completed_tasks_has_null_averages(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create(['completed_at' => null]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $this->assertNull($report->avgLeadTimeMinutes);
        $this->assertNull($report->avgCycleTimeMinutes);
        $this->assertSame(0, $report->completedTaskCount);
        $this->assertSame([], $report->statusBreakdown);
    }

    public function test_lead_time_is_measured_from_creation_to_completion(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create([
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
            'started_at' => null,
            'completed_at' => Carbon::parse('2026-01-03 00:00:00'),
        ]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $this->assertSame(2880.0, $report->avgLeadTimeMinutes);
        $this->assertSame(1, $report->completedTaskCount);
    }

    public function test_cycle_time_is_measured_from_starting_work_to_completion(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create([
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
            'started_at' => Carbon::parse('2026-01-02 00:00:00'),
            'completed_at' => Carbon::parse('2026-01-03 00:00:00'),
        ]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $this->assertSame(1440.0, $report->avgCycleTimeMinutes);
    }

    public function test_a_task_that_never_started_still_counts_toward_lead_time_but_not_cycle_time(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create([
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
            'started_at' => null,
            'completed_at' => Carbon::parse('2026-01-02 00:00:00'),
        ]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $this->assertSame(1, $report->completedTaskCount);
        $this->assertNotNull($report->avgLeadTimeMinutes);
        $this->assertNull($report->avgCycleTimeMinutes);
    }

    public function test_status_breakdown_averages_duration_minutes_grouped_by_the_status_left(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $task->statusHistories()->create([
            'from_status' => TaskStatus::Backlog->value,
            'to_status' => TaskStatus::Todo->value,
            'duration_minutes' => 100,
        ]);
        $task->statusHistories()->create([
            'from_status' => TaskStatus::Backlog->value,
            'to_status' => TaskStatus::Todo->value,
            'duration_minutes' => 200,
        ]);
        $task->statusHistories()->create([
            'from_status' => TaskStatus::InProgress->value,
            'to_status' => TaskStatus::Blocked->value,
            'duration_minutes' => 30,
        ]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $backlogRow = collect($report->statusBreakdown)->firstWhere('status', TaskStatus::Backlog->value);
        $inProgressRow = collect($report->statusBreakdown)->firstWhere('status', TaskStatus::InProgress->value);

        $this->assertSame(150.0, $backlogRow['avgMinutes']);
        $this->assertSame(2, $backlogRow['transitions']);
        $this->assertSame(30.0, $inProgressRow['avgMinutes']);
        $this->assertSame(1, $inProgressRow['transitions']);
    }

    public function test_status_breakdown_is_scoped_to_the_project(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($otherProject)->create();

        $task->statusHistories()->create([
            'from_status' => TaskStatus::Todo->value,
            'to_status' => TaskStatus::InProgress->value,
            'duration_minutes' => 60,
        ]);
        $otherTask->statusHistories()->create([
            'from_status' => TaskStatus::Todo->value,
            'to_status' => TaskStatus::InProgress->value,
            'duration_minutes' => 9999,
        ]);

        $report = app(CalculateCycleTimeReport::class)->handle($project);

        $this->assertCount(1, $report->statusBreakdown);
        $this->assertSame(60.0, $report->statusBreakdown[0]['avgMinutes']);
    }

    public function test_changing_status_records_the_minutes_spent_in_the_previous_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

        Carbon::setTestNow(Carbon::parse('2026-01-01 01:30:00'));
        app(ChangeTaskStatus::class)->handle($task, TaskStatus::InProgress, 0, $user);

        Carbon::setTestNow(null);

        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => TaskStatus::Todo->value,
            'to_status' => TaskStatus::InProgress->value,
            'duration_minutes' => 90,
        ]);
    }
}

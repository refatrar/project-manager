<?php

namespace Tests\Feature\OMS;

use App\Enums\ApprovalStatus;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeLogObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_time_log_immediately_recalculates_the_tasks_logged_hours(): void
    {
        $task = Task::factory()->create(['logged_hours' => 0]);

        TimeLog::factory()->create(['task_id' => $task->id, 'duration_minutes' => 90]);

        $this->assertSame('1.50', $task->fresh()->logged_hours);
    }

    public function test_a_rejected_entry_does_not_count(): void
    {
        $task = Task::factory()->create(['logged_hours' => 0]);

        TimeLog::factory()->create([
            'task_id' => $task->id,
            'duration_minutes' => 90,
            'approval_status' => ApprovalStatus::Rejected,
        ]);

        $this->assertSame('0.00', $task->fresh()->logged_hours);
    }

    public function test_deleting_a_time_log_recalculates_the_task(): void
    {
        $task = Task::factory()->create(['logged_hours' => 0]);
        $timeLog = TimeLog::factory()->create(['task_id' => $task->id, 'duration_minutes' => 90]);
        $this->assertSame('1.50', $task->fresh()->logged_hours);

        $timeLog->delete();

        $this->assertSame('0.00', $task->fresh()->logged_hours);
    }

    public function test_reassigning_a_time_log_to_another_task_recalculates_both(): void
    {
        $project = Project::factory()->create();
        $taskA = Task::factory()->for($project)->create(['logged_hours' => 0]);
        $taskB = Task::factory()->for($project)->create(['logged_hours' => 0]);
        $timeLog = TimeLog::factory()->create(['task_id' => $taskA->id, 'duration_minutes' => 60]);
        $this->assertSame('1.00', $taskA->fresh()->logged_hours);

        $timeLog->task_id = $taskB->id;
        $timeLog->save();

        $this->assertSame('0.00', $taskA->fresh()->logged_hours);
        $this->assertSame('1.00', $taskB->fresh()->logged_hours);
    }

    public function test_a_time_log_with_no_task_does_not_error(): void
    {
        $timeLog = TimeLog::factory()->create(['task_id' => null]);

        $timeLog->description = 'updated';
        $timeLog->save();

        $this->assertTrue(true);
    }
}

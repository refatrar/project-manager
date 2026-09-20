<?php

namespace Tests\Feature\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskStatus;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_number_is_unique_within_a_project(): void
    {
        $project = Project::factory()->create();
        Task::factory()->for($project)->create(['number' => 1]);

        $this->expectException(QueryException::class);

        Task::factory()->for($project)->create(['number' => 1]);
    }

    public function test_the_same_task_number_can_repeat_across_projects(): void
    {
        $first = Task::factory()->for(Project::factory()->create(['code' => 'ALPHA']))->create(['number' => 7]);
        $second = Task::factory()->for(Project::factory()->create(['code' => 'BETA']))->create(['number' => 7]);

        $this->assertSame('ALPHA-7', $first->reference());
        $this->assertSame('BETA-7', $second->reference());
    }

    public function test_a_task_can_belong_to_a_project_without_a_module(): void
    {
        $task = Task::factory()->create();

        $this->assertNull($task->project_module_id);
        $this->assertTrue($task->project->exists);
    }

    public function test_one_user_can_be_assigned_tasks_in_multiple_projects(): void
    {
        $user = User::factory()->create();
        $first = Task::factory()->create();
        $second = Task::factory()->create();

        TaskAssignment::factory()->for($first, 'task')->create(['user_id' => $user->id]);
        TaskAssignment::factory()->for($second, 'task')->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->assignedTasks);
        $this->assertNotSame($first->project_id, $second->project_id);
    }

    public function test_a_task_can_have_several_users_in_different_roles(): void
    {
        $task = Task::factory()->create();
        $developer = User::factory()->create();
        $reviewer = User::factory()->create();

        TaskAssignment::factory()->for($task, 'task')->create(['user_id' => $developer->id]);
        TaskAssignment::factory()->for($task, 'task')->reviewer()->create(['user_id' => $reviewer->id]);

        $this->assertCount(2, $task->assignments);
        $this->assertSame([$developer->id], $task->assignees->pluck('id')->all());
    }

    public function test_the_same_user_cannot_hold_one_role_on_a_task_twice(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();

        TaskAssignment::factory()->for($task, 'task')->create([
            'user_id' => $user->id,
            'role' => TaskAssignmentRole::Assignee,
        ]);

        $this->expectException(QueryException::class);

        TaskAssignment::factory()->for($task, 'task')->create([
            'user_id' => $user->id,
            'role' => TaskAssignmentRole::Assignee,
        ]);
    }

    public function test_the_open_scope_excludes_done_and_cancelled_tasks(): void
    {
        $project = Project::factory()->create();
        $open = Task::factory()->for($project)->inProgress()->create(['number' => 1]);
        Task::factory()->for($project)->done()->create(['number' => 2]);
        Task::factory()->for($project)->create(['number' => 3, 'status' => TaskStatus::Cancelled]);

        $this->assertSame([$open->id], Task::open()->pluck('id')->all());
    }

    public function test_the_overdue_scope_only_returns_open_tasks_past_their_due_date(): void
    {
        $project = Project::factory()->create();
        $overdue = Task::factory()->for($project)->overdue()->create(['number' => 1]);
        Task::factory()->for($project)->create(['number' => 2, 'due_at' => now()->addWeek()]);
        Task::factory()->for($project)->done()->create(['number' => 3, 'due_at' => now()->subWeek()]);

        $this->assertSame([$overdue->id], Task::overdue()->pluck('id')->all());
    }

    public function test_the_assigned_to_scope_ignores_unassigned_records(): void
    {
        $user = User::factory()->create();
        $current = Task::factory()->create();
        $handedOver = Task::factory()->create();

        TaskAssignment::factory()->for($current, 'task')->create(['user_id' => $user->id]);
        TaskAssignment::factory()->for($handedOver, 'task')->reassigned()->create(['user_id' => $user->id]);

        $this->assertSame([$current->id], Task::assignedTo($user)->pluck('id')->all());
    }

    public function test_status_and_priority_are_cast_to_enums(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::ReadyForQa]);

        $this->assertInstanceOf(TaskStatus::class, $task->fresh()->status);
        $this->assertSame('Ready for QA', $task->status->label());
    }
}

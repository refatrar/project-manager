<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\PromoteTodoItemToTask;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Setup\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromoteTodoItemToTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_promoting_a_personal_item_creates_a_standalone_task_with_its_details(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $user->id]);
        $item = TodoItem::factory()->for($list, 'list')->create([
            'title' => 'Buy milk',
            'notes' => 'Whole milk',
            'due_at' => Carbon::parse('2026-10-01'),
        ]);

        $task = app(PromoteTodoItemToTask::class)->handle($item, $project, $user, ['task_type_id' => $taskType->id]);

        $this->assertSame('Buy milk', $task->title);
        $this->assertSame('Whole milk', $task->description);
        $this->assertNull($task->parent_id);
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($task->id, $item->fresh()->task_id);
    }

    public function test_promoting_a_checklist_item_creates_a_subtask_of_the_checklists_task(): void
    {
        $project = Project::factory()->create();
        $taskType = TaskType::factory()->create();
        $parentTask = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($parentTask)->create(['team_id' => $project->team_id]);
        $item = TodoItem::factory()->for($checklist, 'list')->create(['title' => 'Write migration']);
        $user = User::factory()->create();

        $subtask = app(PromoteTodoItemToTask::class)->handle($item, $project, $user, ['task_type_id' => $taskType->id]);

        $this->assertSame($parentTask->id, $subtask->parent_id);
        $this->assertSame($project->id, $subtask->project_id);
    }

    public function test_the_items_assignee_is_carried_over_when_they_are_a_project_member(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $assignee = User::factory()->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $assignee->id]);
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $user->id]);
        $item = TodoItem::factory()->for($list, 'list')->create(['assigned_to' => $assignee->id]);

        $task = app(PromoteTodoItemToTask::class)->handle($item, $project, $user, ['task_type_id' => $taskType->id]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
    }

    public function test_the_items_assignee_is_not_carried_over_when_they_are_not_a_project_member(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $outsider = User::factory()->create();
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $user->id]);
        $item = TodoItem::factory()->for($list, 'list')->create(['assigned_to' => $outsider->id]);

        $task = app(PromoteTodoItemToTask::class)->handle($item, $project, $user, ['task_type_id' => $taskType->id]);

        $this->assertDatabaseCount('task_assignments', 0);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}

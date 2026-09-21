<?php

namespace Tests\Feature\OMS;

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_can_be_added_to_the_users_own_list(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.store', $list), [
                'title' => 'Buy milk',
                'priority' => 'medium',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.title', 'Buy milk');

        $this->assertDatabaseHas('todo_items', [
            'todo_list_id' => $list->id,
            'title' => 'Buy milk',
            'created_by' => $user->id,
        ]);
    }

    public function test_an_item_can_be_assigned_to_a_team_member(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $user->currentTeam->members()->attach($assignee, ['role' => TeamRole::Member->value]);
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.store', $list), [
                'title' => 'Review PR',
                'priority' => 'high',
                'assigned_to' => $assignee->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.assignee.id', $assignee->id);
    }

    public function test_the_literal_none_placeholder_for_assigned_to_is_treated_as_unassigned(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.store', $list), [
                'title' => 'Buy milk',
                'priority' => 'medium',
                'assigned_to' => 'none',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.assignee', null);
    }

    public function test_a_user_cannot_add_an_item_to_someone_elses_list(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $user->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.store', $list), [
                'title' => 'Buy milk',
                'priority' => 'medium',
            ]);

        $response->assertForbidden();
    }

    public function test_toggling_an_item_complete_stamps_who_and_when(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $item = TodoItem::factory()->for($list, 'list')->create(['is_completed' => false]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->itemRoute($user, 'todo-lists.items.toggle', $list, $item), [
                'is_completed' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('todo_items', [
            'id' => $item->id,
            'is_completed' => true,
            'completed_by' => $user->id,
        ]);
    }

    public function test_completing_an_item_linked_to_a_task_closes_the_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $item = TodoItem::factory()->for($list, 'list')->create([
            'task_id' => $task->id,
            'is_completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->itemRoute($user, 'todo-lists.items.toggle', $list, $item), [
                'is_completed' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::Done->value]);
    }

    public function test_un_completing_an_item_does_not_reopen_its_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Done]);
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $item = TodoItem::factory()->for($list, 'list')->create([
            'task_id' => $task->id,
            'is_completed' => true,
            'completed_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->itemRoute($user, 'todo-lists.items.toggle', $list, $item), [
                'is_completed' => false,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('todo_items', ['id' => $item->id, 'is_completed' => false, 'completed_by' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::Done->value]);
    }

    public function test_an_item_can_be_removed(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $item = TodoItem::factory()->for($list, 'list')->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->itemRoute($user, 'todo-lists.items.destroy', $list, $item));

        $response->assertOk();
        $this->assertDatabaseMissing('todo_items', ['id' => $item->id]);
    }

    public function test_an_item_from_another_list_cannot_be_reached_through_this_lists_url(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $otherList = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);
        $item = TodoItem::factory()->for($otherList, 'list')->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->itemRoute($user, 'todo-lists.items.destroy', $list, $item));

        $response->assertNotFound();
    }

    public function test_a_project_member_can_add_an_item_to_a_tasks_checklist(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $member->id]);
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $owner->currentTeam->id]);

        $response = $this
            ->actingAs($member)
            ->postJson($this->itemRoute($member, 'todo-lists.items.store', $checklist, null, $owner->currentTeam), [
                'title' => 'Write tests',
                'priority' => 'medium',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('todo_items', ['todo_list_id' => $checklist->id, 'title' => 'Write tests']);
    }

    public function test_a_non_project_member_cannot_add_an_item_to_a_tasks_checklist(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->currentTeam->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $owner->currentTeam->id]);

        $response = $this
            ->actingAs($outsider)
            ->postJson($this->itemRoute($outsider, 'todo-lists.items.store', $checklist, null, $owner->currentTeam), [
                'title' => 'Write tests',
                'priority' => 'medium',
            ]);

        $response->assertForbidden();
    }

    public function test_a_team_admin_can_manage_a_checklist_without_being_a_project_member(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $owner->currentTeam->id]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->itemRoute($owner, 'todo-lists.items.store', $checklist), [
                'title' => 'Write tests',
                'priority' => 'medium',
            ]);

        $response->assertCreated();
    }

    public function test_promoting_a_personal_item_requires_a_project(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $user->id]);
        $item = TodoItem::factory()->for($list, 'list')->create();
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.promote', $list, $item), [
                'task_type_id' => $taskType->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_promoting_a_personal_item_with_a_project_creates_a_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $user->id]);
        $item = TodoItem::factory()->for($list, 'list')->create(['title' => 'Ship the feature']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.promote', $list, $item), [
                'project_id' => $project->id,
                'task_type_id' => $taskType->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.task.title', 'Ship the feature');
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Ship the feature']);
    }

    public function test_promoting_a_checklist_item_does_not_require_a_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $owner->currentTeam->id]);
        $item = TodoItem::factory()->for($checklist, 'list')->create();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->itemRoute($owner, 'todo-lists.items.promote', $checklist, $item), [
                'task_type_id' => $taskType->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'parent_id' => $task->id]);
    }

    public function test_a_project_member_can_promote_a_checklist_item(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $member->id]);
        $taskType = TaskType::factory()->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $owner->currentTeam->id]);
        $item = TodoItem::factory()->for($checklist, 'list')->create();

        $response = $this
            ->actingAs($member)
            ->postJson($this->itemRoute($member, 'todo-lists.items.promote', $checklist, $item, $owner->currentTeam), [
                'task_type_id' => $taskType->id,
            ]);

        $response->assertCreated();
    }

    public function test_a_user_cannot_promote_someone_elses_personal_item(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $user->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $list = TodoList::factory()->create(['team_id' => $user->currentTeam->id, 'owner_id' => $owner->id]);
        $item = TodoItem::factory()->for($list, 'list')->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->itemRoute($user, 'todo-lists.items.promote', $list, $item), [
                'project_id' => $project->id,
                'task_type_id' => $taskType->id,
            ]);

        $response->assertForbidden();
    }

    private function itemRoute(User $user, string $name, TodoList $list, ?TodoItem $item = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'todo_list' => $list,
            'item' => $item,
        ]));
    }
}

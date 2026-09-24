<?php

namespace Tests\Feature\OMS;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TodoListType;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TaskDependency;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Setup\Label;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_active_member_can_create_a_task(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($developer)
            ->postJson($this->taskRoute($owner, 'projects.tasks.store', $project, team: $team), [
                'title' => 'Set up CI',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('task.title', 'Set up CI');
        $response->assertJsonPath('task.reference', $project->fresh()->code.'-1');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Set up CI',
            'number' => 1,
        ]);
        $this->assertSame(2, $project->fresh()->next_task_number);
    }

    public function test_creating_a_task_with_the_forms_literal_none_placeholders_succeeds(): void
    {
        // Regression test: the React form's default payload sends the literal
        // string 'none' for parent_id and project_module_id when nothing is
        // selected, not an empty value. prepareForValidation() must normalize
        // both to null or this 422s on every plain "create task" click.
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => 'Plain task',
                'task_type_id' => $taskType->id,
                'project_module_id' => 'none',
                'parent_id' => 'none',
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Plain task',
            'project_module_id' => null,
            'parent_id' => null,
        ]);
    }

    public function test_a_task_can_be_created_with_labels_a_milestone_and_a_sprint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $label = Label::factory()->create(['team_id' => $user->currentTeam->id]);
        $milestone = Milestone::factory()->for($project)->create();
        $sprint = Sprint::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => 'Tagged task',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
                'milestone_id' => $milestone->id,
                'sprint_id' => $sprint->id,
                'label_ids' => [$label->id],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('task.milestone_id', $milestone->id);
        $response->assertJsonPath('task.sprint_id', $sprint->id);
        $response->assertJsonPath('task.labels.0.id', $label->id);

        $this->assertDatabaseHas('label_task', [
            'task_id' => $response->json('task.id'),
            'label_id' => $label->id,
        ]);
    }

    public function test_a_milestone_from_another_project_cannot_be_assigned(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();
        $foreignMilestone = Milestone::factory()->for(Project::factory()->for($user->currentTeam))->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => 'Should fail',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
                'milestone_id' => $foreignMilestone->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['milestone_id']);
    }

    public function test_task_numbers_increment_per_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->actingAs($user)->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => $title,
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ])->assertCreated();
        }

        $numbers = Task::where('project_id', $project->id)->orderBy('id')->pluck('number')->all();
        $this->assertSame([1, 2, 3], $numbers);
    }

    public function test_a_non_member_cannot_create_a_task(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $outsider = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($outsider)
            ->postJson($this->taskRoute($owner, 'projects.tasks.store', $project), [
                'title' => 'Should fail',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertForbidden();
    }

    public function test_a_developer_cannot_edit_a_task_they_do_not_manage(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($developer)
            ->putJson($this->taskRoute($owner, 'projects.tasks.update', $project, $task, team: $team), [
                'title' => 'Renamed',
                'task_type_id' => $task->task_type_id,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertForbidden();
    }

    public function test_an_assignee_can_move_their_own_task_without_manage_rights(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($developer)
            ->patchJson($this->taskRoute($owner, 'projects.tasks.move', $project, $task, team: $team), [
                'status' => TaskStatus::InProgress->value,
                'position' => 0,
            ]);

        $response->assertOk();
        $response->assertJsonPath('task.status', TaskStatus::InProgress->value);
        $this->assertNotNull($task->fresh()->started_at);
    }

    public function test_a_non_assignee_developer_cannot_move_the_task(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($developer)
            ->patchJson($this->taskRoute($owner, 'projects.tasks.move', $project, $task, team: $team), [
                'status' => TaskStatus::InProgress->value,
                'position' => 0,
            ]);

        $response->assertForbidden();
    }

    public function test_moving_to_done_writes_a_status_history_row_and_stamps_completed_at(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::ReadyForQa]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $task), [
                'status' => TaskStatus::Done->value,
                'position' => 0,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => TaskStatus::ReadyForQa->value,
            'to_status' => TaskStatus::Done->value,
        ]);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_moving_to_the_same_status_does_not_duplicate_history(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);

        $this->actingAs($user)->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $task), [
            'status' => TaskStatus::Todo->value,
            'position' => 3,
        ])->assertOk();

        $this->assertDatabaseCount('task_status_histories', 0);
        // The only task in its column: an out-of-range slot clamps to 0.
        $this->assertSame(0, $task->fresh()->position);
    }

    public function test_moving_a_task_up_swaps_it_with_the_card_above(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $first = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);
        $second = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 1]);
        $third = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 2]);

        $this->actingAs($user)->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $third), [
            'status' => TaskStatus::Todo->value,
            'position' => 1,
        ])->assertOk();

        $this->assertSame([$first->id, $third->id, $second->id], $this->columnOrder($project, TaskStatus::Todo));
    }

    public function test_moving_a_task_down_swaps_it_with_the_card_below(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $first = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);
        $second = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 1]);
        $third = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 2]);

        $this->actingAs($user)->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $first), [
            'status' => TaskStatus::Todo->value,
            'position' => 1,
        ])->assertOk();

        $this->assertSame([$second->id, $first->id, $third->id], $this->columnOrder($project, TaskStatus::Todo));
    }

    public function test_reordering_untangles_tasks_that_share_a_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $first = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);
        $second = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);

        $this->actingAs($user)->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $second), [
            'status' => TaskStatus::Todo->value,
            'position' => 0,
        ])->assertOk();

        $this->assertSame([$second->id, $first->id], $this->columnOrder($project, TaskStatus::Todo));
        $this->assertSame([0, 1], Task::query()->where('project_id', $project->id)->orderBy('position')->pluck('position')->all());
    }

    public function test_moving_a_task_to_another_column_appends_it_there(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $existing = Task::factory()->for($project)->create(['status' => TaskStatus::InProgress, 'position' => 0]);
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);

        $this->actingAs($user)->patchJson($this->taskRoute($user, 'projects.tasks.move', $project, $task), [
            'status' => TaskStatus::InProgress->value,
            'position' => 1,
        ])->assertOk();

        $this->assertSame([$existing->id, $task->id], $this->columnOrder($project, TaskStatus::InProgress));
    }

    public function test_a_task_cannot_be_reached_through_another_projects_url(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($otherProject)->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->taskRoute($user, 'projects.tasks.destroy', $project, $task));

        $response->assertNotFound();
    }

    public function test_a_task_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->taskRoute($user, 'projects.tasks.destroy', $project, $task));

        $response->assertOk();
        $this->assertSoftDeleted($task);
    }

    public function test_the_task_detail_page_renders_subtasks_and_dependencies(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['title' => 'Parent task']);
        $subtask = Task::factory()->for($project)->create(['parent_id' => $task->id, 'title' => 'Subtask one']);
        $blocker = Task::factory()->for($project)->create();
        TaskDependency::factory()->create([
            'task_id' => $task->id,
            'related_task_id' => $blocker->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->taskRoute($user, 'projects.tasks.show', $project, $task));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('projects/tasks/show')
            ->where('task.title', 'Parent task')
            ->has('task.subtasks', 1)
            ->where('task.subtasks.0.title', 'Subtask one')
            ->has('task.dependencies', 1)
            ->where('task.dependencies.0.relatedTask.id', $blocker->id),
        );
    }

    public function test_the_task_detail_page_creates_and_returns_the_tasks_checklist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->get($this->taskRoute($user, 'projects.tasks.show', $project, $task));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('checklist.type', 'task_checklist')
            ->has('checklist.items', 0),
        );

        $this->assertDatabaseHas('todo_lists', [
            'task_id' => $task->id,
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'type' => 'task_checklist',
        ]);
        $this->assertDatabaseCount('todo_lists', 1);
    }

    public function test_visiting_the_task_detail_page_twice_does_not_duplicate_the_checklist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)->get($this->taskRoute($user, 'projects.tasks.show', $project, $task));
        $this->actingAs($user)->get($this->taskRoute($user, 'projects.tasks.show', $project, $task));

        $this->assertDatabaseCount('todo_lists', 1);
    }

    public function test_a_non_member_cannot_view_the_task(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $response = $this
            ->actingAs($outsider)
            ->get($this->taskRoute($owner, 'projects.tasks.show', $project, $task));

        $response->assertForbidden();
    }

    public function test_a_task_detail_page_cannot_be_reached_through_another_teams_url(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $project = Project::factory()->for($otherTeam)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->get($this->taskRoute($user, 'projects.tasks.show', $project, $task));

        $response->assertNotFound();
    }

    public function test_a_task_can_be_created_with_multiple_todos(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => 'Release',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
                'todos' => [
                    ['title' => 'Tag the build'],
                    ['title' => '   '],
                    ['title' => 'Write release notes'],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'task.todos');
        $response->assertJsonPath('task.todos.0.title', 'Tag the build');
        $response->assertJsonPath('task.todos.1.title', 'Write release notes');

        $task = Task::query()->where('title', 'Release')->firstOrFail();
        $checklist = TodoList::query()->where('task_id', $task->id)->where('type', TodoListType::TaskChecklist->value)->sole();
        $this->assertSame(['Tag the build', 'Write release notes'], $checklist->items->pluck('title')->all());
        $this->assertSame($user->id, $checklist->items->first()->created_by);
    }

    public function test_creating_a_task_without_todos_creates_no_checklist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskType = TaskType::factory()->create();

        $this
            ->actingAs($user)
            ->postJson($this->taskRoute($user, 'projects.tasks.store', $project), [
                'title' => 'No todos',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
                'todos' => [],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('todo_lists', 0);
    }

    public function test_updating_a_task_syncs_its_todos(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $project->team_id]);
        $kept = TodoItem::factory()->for($checklist, 'list')->create(['title' => 'Old title', 'position' => 0, 'is_completed' => true]);
        $removed = TodoItem::factory()->for($checklist, 'list')->create(['title' => 'Drop me', 'position' => 1]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->taskRoute($user, 'projects.tasks.update', $project, $task), [
                'title' => $task->title,
                'task_type_id' => $task->task_type_id,
                'priority' => Priority::Medium->value,
                'todos' => [
                    ['title' => 'Brand new'],
                    ['id' => $kept->id, 'title' => 'Renamed'],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('task.todos.0.title', 'Brand new');
        $response->assertJsonPath('task.todos.1.id', $kept->id);
        $response->assertJsonPath('task.todos.1.is_completed', true);

        $this->assertDatabaseMissing('todo_items', ['id' => $removed->id]);
        $this->assertDatabaseHas('todo_items', ['id' => $kept->id, 'title' => 'Renamed', 'position' => 1, 'is_completed' => true]);
        $this->assertDatabaseHas('todo_items', ['todo_list_id' => $checklist->id, 'title' => 'Brand new', 'position' => 0]);
    }

    public function test_updating_a_task_without_todos_leaves_its_checklist_untouched(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $project->team_id]);
        $item = TodoItem::factory()->for($checklist, 'list')->create();

        $this
            ->actingAs($user)
            ->putJson($this->taskRoute($user, 'projects.tasks.update', $project, $task), [
                'title' => 'Renamed task',
                'task_type_id' => $task->task_type_id,
                'priority' => Priority::Medium->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('todo_items', ['id' => $item->id]);
    }

    public function test_a_todo_id_from_another_tasks_checklist_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $otherChecklist = TodoList::factory()->checklistFor($otherTask)->create(['team_id' => $project->team_id]);
        $foreignItem = TodoItem::factory()->for($otherChecklist, 'list')->create(['title' => 'Not yours']);

        $this
            ->actingAs($user)
            ->putJson($this->taskRoute($user, 'projects.tasks.update', $project, $task), [
                'title' => $task->title,
                'task_type_id' => $task->task_type_id,
                'priority' => Priority::Medium->value,
                'todos' => [['id' => $foreignItem->id, 'title' => 'Hijacked']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('todos.0.id');

        $this->assertDatabaseHas('todo_items', ['id' => $foreignItem->id, 'title' => 'Not yours']);
    }

    /**
     * @return list<int>
     */
    private function columnOrder(Project $project, TaskStatus $status): array
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->where('status', $status->value)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function taskRoute(User $user, string $name, Project $project, ?Task $task = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
        ]));
    }
}

<?php

namespace Tests\Feature\OMS;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TaskDependency;
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
        $this->assertSame(3, $task->fresh()->position);
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

    private function taskRoute(User $user, string $name, Project $project, ?Task $task = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
        ]));
    }
}

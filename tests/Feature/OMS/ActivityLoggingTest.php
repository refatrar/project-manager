<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\AddTaskDependency;
use App\Actions\OMS\AssignTask;
use App\Actions\OMS\ChangeTaskStatus;
use App\Actions\OMS\RecordActivity;
use App\Enums\TaskAssignmentRole;
use App\Enums\TaskDependencyType;
use App\Enums\TaskStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_activity_stamps_team_project_user_and_subject(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $activity = app(RecordActivity::class)->handle(
            team: $project->team,
            project: $project,
            userId: $task->created_by,
            event: 'task.created',
            description: 'A test activity',
            subject: $task,
        );

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'user_id' => $task->created_by,
            'event' => 'task.created',
            'description' => 'A test activity',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
        ]);
    }

    public function test_record_activity_allows_a_null_project(): void
    {
        $project = Project::factory()->create();

        $activity = app(RecordActivity::class)->handle(
            team: $project->team,
            project: null,
            userId: null,
            event: 'team.something',
            description: 'Team level event',
        );

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'project_id' => null,
            'user_id' => null,
        ]);
    }

    public function test_creating_and_deleting_a_project_is_logged(): void
    {
        $project = Project::factory()->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'project.created',
        ]);

        $project->deleted_by = $project->owner_id;
        $project->save();
        $project->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'project.deleted',
        ]);
    }

    public function test_creating_and_deleting_a_module_is_logged(): void
    {
        $project = Project::factory()->create();
        $module = ProjectModule::factory()->for($project)->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'module.created',
            'subject_type' => ProjectModule::class,
            'subject_id' => $module->id,
        ]);

        $module->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'module.deleted',
        ]);
    }

    public function test_adding_and_removing_a_member_is_logged(): void
    {
        $project = Project::factory()->create();
        $member = ProjectMember::factory()->for($project)->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'member.added',
            'subject_type' => ProjectMember::class,
            'subject_id' => $member->id,
        ]);

        $member->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'member.removed',
        ]);
    }

    public function test_creating_and_deleting_a_task_is_logged(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'task.created',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
        ]);

        $task->deleted_by = $task->created_by;
        $task->save();
        $task->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'task.deleted',
        ]);
    }

    public function test_creating_and_deleting_a_milestone_is_logged(): void
    {
        $project = Project::factory()->create();
        $milestone = Milestone::factory()->for($project)->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'milestone.created',
            'subject_type' => Milestone::class,
            'subject_id' => $milestone->id,
        ]);

        $milestone->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'milestone.deleted',
        ]);
    }

    public function test_creating_and_deleting_a_sprint_is_logged(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->for($project)->create();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'sprint.created',
            'subject_type' => Sprint::class,
            'subject_id' => $sprint->id,
        ]);

        $sprint->delete();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'sprint.deleted',
        ]);
    }

    public function test_a_task_status_change_is_logged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

        app(ChangeTaskStatus::class)->handle($task, TaskStatus::InProgress, 0, $user);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'task.status_changed',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_moving_to_the_same_status_does_not_log_an_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

        app(ChangeTaskStatus::class)->handle($task, TaskStatus::Todo, 1, $user);

        $this->assertDatabaseMissing('activities', [
            'project_id' => $project->id,
            'event' => 'task.status_changed',
        ]);
    }

    public function test_assigning_and_unassigning_a_task_is_logged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $assignment = app(AssignTask::class)->assign($task, $user->id, TaskAssignmentRole::Assignee, null, $user);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'task.assigned',
            'subject_type' => $assignment::class,
            'subject_id' => $assignment->id,
        ]);

        app(AssignTask::class)->unassign($assignment, $user);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'task.unassigned',
            'subject_id' => $assignment->id,
        ]);
    }

    public function test_adding_a_task_dependency_is_logged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $blocker = Task::factory()->for($project)->create();

        $dependency = app(AddTaskDependency::class)->handle($task, $blocker, TaskDependencyType::BlockedBy, $user);

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'dependency.added',
            'subject_type' => $dependency::class,
            'subject_id' => $dependency->id,
        ]);
    }

    public function test_removing_a_task_dependency_is_logged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $blocker = Task::factory()->for($project)->create();
        $dependency = app(AddTaskDependency::class)->handle($task, $blocker, TaskDependencyType::BlockedBy, $user);

        $this
            ->actingAs($user)
            ->deleteJson(route('projects.tasks.dependencies.destroy', [
                'current_team' => $user->currentTeam->slug,
                'project' => $project,
                'task' => $task,
                'dependency' => $dependency,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'dependency.removed',
        ]);
    }

    public function test_archiving_a_project_is_logged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $this
            ->actingAs($user)
            ->patchJson(route('projects.archive', [
                'current_team' => $user->currentTeam->slug,
                'project' => $project,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('activities', [
            'project_id' => $project->id,
            'event' => 'project.archived',
            'user_id' => $user->id,
        ]);
    }

    public function test_the_project_workspace_returns_the_recent_activity_feed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->get(route('projects.show', [
                'current_team' => $user->currentTeam->slug,
                'project' => $project,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('activities', 2)
            ->where('activities.0.event', 'member.added')
            ->where('activities.1.event', 'project.created'));
    }
}

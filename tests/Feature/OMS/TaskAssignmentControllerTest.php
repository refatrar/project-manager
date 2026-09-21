<?php

namespace Tests\Feature\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_assign_a_project_member_to_a_task(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task), [
                'user_id' => $developer->id,
                'role' => TaskAssignmentRole::Assignee->value,
                'allocated_hours' => 6,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('assignment.user.id', $developer->id);
        $response->assertJsonPath('assignment.role', TaskAssignmentRole::Assignee->value);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'user_id' => $developer->id,
            'role' => TaskAssignmentRole::Assignee->value,
            'assigned_by' => $owner->id,
        ]);
    }

    public function test_assigning_a_non_project_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task), [
                'user_id' => $outsider->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_a_user_cannot_hold_the_same_role_twice_while_active(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $developer = User::factory()->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $developer->id, 'role' => TaskAssignmentRole::Assignee]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task), [
                'user_id' => $developer->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('task_assignments', 1);
    }

    public function test_multiple_different_users_can_hold_the_same_role_simultaneously(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();

        $first = User::factory()->create();
        $second = User::factory()->create();
        $team->members()->attach([$first->id, $second->id], ['role' => TeamRole::Member->value]);
        ProjectMember::factory()->for($project)->create(['user_id' => $first->id]);
        ProjectMember::factory()->for($project)->create(['user_id' => $second->id]);

        foreach ([$first, $second] as $user) {
            $this->actingAs($owner)->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task), [
                'user_id' => $user->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('task_assignments', 2);
    }

    public function test_unassigning_preserves_the_row_as_history(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $assignment = TaskAssignment::factory()->for($task)->create();

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->assignmentRoute($owner, 'projects.tasks.assignments.destroy', $project, $task, $assignment));

        $response->assertOk();

        $this->assertDatabaseCount('task_assignments', 1);
        $this->assertDatabaseHas('task_assignments', [
            'id' => $assignment->id,
            'status' => TaskAssignmentStatus::Reassigned->value,
        ]);
        $this->assertNotNull($assignment->fresh()->unassigned_at);
    }

    public function test_re_assigning_the_same_role_after_unassignment_reactivates_the_row(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $developer = User::factory()->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $original = TaskAssignment::factory()->for($task)->create([
            'user_id' => $developer->id,
            'role' => TaskAssignmentRole::Assignee,
            'status' => TaskAssignmentStatus::Reassigned,
            'unassigned_at' => now(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task), [
                'user_id' => $developer->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('assignment.id', $original->id);
        $this->assertDatabaseCount('task_assignments', 1);
        $this->assertDatabaseHas('task_assignments', [
            'id' => $original->id,
            'status' => TaskAssignmentStatus::Assigned->value,
            'unassigned_at' => null,
        ]);
    }

    public function test_a_developer_without_manage_rights_cannot_assign(): void
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
            ->postJson($this->assignmentRoute($owner, 'projects.tasks.assignments.store', $project, $task, team: $team), [
                'user_id' => $developer->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);

        $response->assertForbidden();
    }

    public function test_an_assignment_cannot_be_reached_through_another_tasks_url(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $assignment = TaskAssignment::factory()->for($otherTask)->create();

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->assignmentRoute($owner, 'projects.tasks.assignments.destroy', $project, $task, $assignment));

        $response->assertNotFound();
    }

    private function assignmentRoute(User $user, string $name, Project $project, Task $task, ?TaskAssignment $assignment = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
            'assignment' => $assignment,
        ]));
    }
}

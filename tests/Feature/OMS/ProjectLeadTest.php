<?php

namespace Tests\Feature\OMS;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskAssignmentRole;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Task;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_with_a_project_lead(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $lead = User::factory()->create();
        $team->members()->attach($lead, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($teamLead)
            ->postJson($this->projectsRoute($teamLead, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Alpha Platform',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
                'project_lead_id' => $lead->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('projects', [
            'code' => 'ALPHA',
            'project_lead_id' => $lead->id,
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $response->json('project.id'),
            'user_id' => $lead->id,
            'role' => ProjectMemberRole::Lead->value,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $response->json('project.id'),
            'user_id' => $teamLead->id,
            'role' => ProjectMemberRole::Owner->value,
        ]);
    }

    public function test_project_lead_can_be_left_unset(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Alpha Platform',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('projects', [
            'code' => 'ALPHA',
            'project_lead_id' => null,
        ]);
    }

    public function test_a_user_outside_the_team_cannot_be_assigned_as_project_lead(): void
    {
        $user = User::factory()->create();
        $outsider = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Alpha Platform',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
                'project_lead_id' => $outsider->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['project_lead_id']);
    }

    public function test_project_lead_can_create_and_assign_tasks_without_a_managing_project_role(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $lead = User::factory()->create();
        $team->members()->attach($lead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $lead->id]);
        // Deliberately not a project member at all, let alone a managing one.
        $taskType = TaskType::factory()->create();

        $createResponse = $this
            ->actingAs($lead)
            ->postJson($this->taskRoute($teamLead, 'projects.tasks.store', $project, team: $team), [
                'title' => 'Set up CI',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ]);
        $createResponse->assertCreated();
        $task = Task::where('project_id', $project->id)->firstOrFail();

        $assignee = User::factory()->create();
        $team->members()->attach($assignee, ['role' => TeamRole::Member->value]);
        ProjectMember::factory()->for($project)->create(['user_id' => $assignee->id]);

        $assignResponse = $this
            ->actingAs($lead)
            ->postJson($this->assignmentRoute($teamLead, 'projects.tasks.assignments.store', $project, $task, $team), [
                'user_id' => $assignee->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);
        $assignResponse->assertCreated();
    }

    public function test_project_lead_can_update_the_project(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $lead = User::factory()->create();
        $team->members()->attach($lead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $lead->id]);

        $response = $this
            ->actingAs($lead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Renamed by lead',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('project.name', 'Renamed by lead');
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $lead->id,
            'role' => ProjectMemberRole::Lead->value,
        ]);
    }

    public function test_updating_the_project_lead_adds_them_without_changing_an_existing_role(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $currentLead = User::factory()->create();
        $nextLead = User::factory()->create();
        $team->members()->attach($currentLead, ['role' => TeamRole::Member->value]);
        $team->members()->attach($nextLead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $currentLead->id]);
        ProjectMember::factory()->for($project)->create([
            'user_id' => $nextLead->id,
            'role' => ProjectMemberRole::Developer,
        ]);

        $response = $this
            ->actingAs($teamLead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', project: $project), [
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status->value,
                'priority' => $project->priority->value,
                'health' => $project->health->value,
                'project_lead_id' => $nextLead->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $nextLead->id,
            'role' => ProjectMemberRole::Developer->value,
        ]);
        $this->assertSame(1, ProjectMember::query()->where('project_id', $project->id)->where('user_id', $nextLead->id)->count());
    }

    public function test_project_lead_can_manage_meetings_and_attendees_without_being_organizer(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $lead = User::factory()->create();
        $team->members()->attach($lead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $lead->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $teamLead->id,
        ]);

        $updateResponse = $this
            ->actingAs($lead)
            ->putJson($this->meetingsRoute($teamLead, 'meetings.update', $meeting, $team), [
                'title' => 'Led update',
                'type' => $meeting->type->value,
                'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
                'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
            ]);
        $updateResponse->assertOk();

        $invitee = User::factory()->create();
        $team->members()->attach($invitee, ['role' => TeamRole::Member->value]);

        $attendeeResponse = $this
            ->actingAs($lead)
            ->postJson($this->attendeeRoute($teamLead, 'meetings.attendees.store', $meeting, $team), [
                'user_id' => $invitee->id,
                'role' => 'participant',
            ]);
        $attendeeResponse->assertCreated();
    }

    public function test_team_lead_can_perform_project_lead_operations_without_being_a_project_member(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $otherMember = User::factory()->create();
        $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        // team lead deliberately not added as a project member.
        $taskType = TaskType::factory()->create();

        $response = $this
            ->actingAs($teamLead)
            ->postJson($this->taskRoute($teamLead, 'projects.tasks.store', $project), [
                'title' => 'Team lead task',
                'task_type_id' => $taskType->id,
                'status' => TaskStatus::Backlog->value,
                'priority' => Priority::Medium->value,
            ]);
        $response->assertCreated();

        $updateResponse = $this
            ->actingAs($teamLead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', project: $project), [
                'code' => $project->code,
                'name' => 'Renamed by team lead',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);
        $updateResponse->assertOk();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $teamLead->id,
            'role' => ProjectMemberRole::Lead->value,
            'status' => 'active',
        ]);
    }

    public function test_team_lead_from_another_team_cannot_manage_the_project(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $project = Project::factory()->for($team)->create();

        $otherTeamLead = User::factory()->create();

        $response = $this
            ->actingAs($otherTeamLead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Hijacked',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertForbidden();
    }

    public function test_a_normal_member_cannot_assign_tasks_or_manage_meetings(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $member->id, 'role' => ProjectMemberRole::Developer]);
        $task = Task::factory()->for($project)->create();
        $assignee = User::factory()->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $assignee->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $teamLead->id,
        ]);

        $assignResponse = $this
            ->actingAs($member)
            ->postJson($this->assignmentRoute($teamLead, 'projects.tasks.assignments.store', $project, $task, $team), [
                'user_id' => $assignee->id,
                'role' => TaskAssignmentRole::Assignee->value,
            ]);
        $assignResponse->assertForbidden();

        $meetingResponse = $this
            ->actingAs($member)
            ->putJson($this->meetingsRoute($teamLead, 'meetings.update', $meeting, $team), [
                'title' => 'Sneaky update',
                'type' => $meeting->type->value,
                'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
                'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
            ]);
        $meetingResponse->assertForbidden();
    }

    public function test_removing_the_project_lead_from_the_team_revokes_their_authority(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $lead = User::factory()->create();
        $team->members()->attach($lead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $lead->id]);

        $team->members()->where('user_id', $lead->id)->delete();

        $response = $this
            ->actingAs($lead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Should not work',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertForbidden();
    }

    public function test_changing_the_project_lead_transfers_authority(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $formerLead = User::factory()->create();
        $newLead = User::factory()->create();
        $team->members()->attach($formerLead, ['role' => TeamRole::Member->value]);
        $team->members()->attach($newLead, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create(['project_lead_id' => $formerLead->id]);

        $project->update(['project_lead_id' => $newLead->id]);

        $formerLeadResponse = $this
            ->actingAs($formerLead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Former lead edit',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);
        $formerLeadResponse->assertForbidden();

        $newLeadResponse = $this
            ->actingAs($newLead)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'New lead edit',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);
        $newLeadResponse->assertOk();
    }

    public function test_an_existing_managing_project_role_still_works_unaffected_by_project_lead(): void
    {
        $teamLead = User::factory()->create();
        $team = $teamLead->currentTeam;
        $manager = User::factory()->create();
        $team->members()->attach($manager, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->manager()->create(['user_id' => $manager->id]);

        $response = $this
            ->actingAs($manager)
            ->putJson($this->projectsRoute($teamLead, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Manager edit',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertOk();
    }

    private function projectsRoute(User $user, string $name, ?Team $team = null, ?Project $project = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
        ]));
    }

    private function taskRoute(User $user, string $name, Project $project, ?Task $task = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
        ]));
    }

    private function assignmentRoute(User $user, string $name, Project $project, Task $task, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
        ]));
    }

    private function meetingsRoute(User $user, string $name, ?Meeting $meeting = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'meeting' => $meeting,
        ]));
    }

    private function attendeeRoute(User $user, string $name, Meeting $meeting, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'meeting' => $meeting,
        ]));
    }
}

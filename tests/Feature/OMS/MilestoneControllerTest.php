<?php

namespace Tests\Feature\OMS;

use App\Enums\MilestoneStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_owner_can_create_a_milestone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->milestoneRoute($user, 'projects.milestones.store', $project), [
                'name' => 'Beta launch',
                'status' => MilestoneStatus::Pending->value,
                'due_on' => now()->addMonth()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('milestone.name', 'Beta launch');

        $this->assertDatabaseHas('milestones', [
            'project_id' => $project->id,
            'name' => 'Beta launch',
            'created_by' => $user->id,
        ]);
    }

    public function test_position_increments_across_milestones(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        Milestone::factory()->for($project)->create(['position' => 0]);
        Milestone::factory()->for($project)->create(['position' => 1]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->milestoneRoute($user, 'projects.milestones.store', $project), [
                'name' => 'Third milestone',
                'status' => MilestoneStatus::Pending->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('milestone.position', 2);
    }

    public function test_a_developer_without_manage_rights_cannot_create_a_milestone(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($developer)
            ->postJson($this->milestoneRoute($owner, 'projects.milestones.store', $project, team: $team), [
                'name' => 'Should fail',
                'status' => MilestoneStatus::Pending->value,
            ]);

        $response->assertForbidden();
    }

    public function test_a_milestone_can_be_updated(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $milestone = Milestone::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->milestoneRoute($user, 'projects.milestones.update', $project, $milestone), [
                'name' => 'Renamed milestone',
                'status' => MilestoneStatus::InProgress->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('milestone.name', 'Renamed milestone');
        $response->assertJsonPath('milestone.status', MilestoneStatus::InProgress->value);
    }

    public function test_a_milestone_cannot_be_reached_through_another_projects_url(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $milestone = Milestone::factory()->for($otherProject)->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->milestoneRoute($user, 'projects.milestones.update', $project, $milestone), [
                'name' => 'Hijacked',
                'status' => MilestoneStatus::Pending->value,
            ]);

        $response->assertNotFound();
    }

    public function test_a_milestone_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $milestone = Milestone::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->milestoneRoute($user, 'projects.milestones.destroy', $project, $milestone));

        $response->assertOk();
        $this->assertSoftDeleted($milestone);
    }

    private function milestoneRoute(User $user, string $name, Project $project, ?Milestone $milestone = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'milestone' => $milestone,
        ]));
    }
}

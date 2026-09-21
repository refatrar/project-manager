<?php

namespace Tests\Feature\OMS;

use App\Enums\SprintStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_owner_can_create_a_sprint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->sprintRoute($user, 'projects.sprints.store', $project), [
                'name' => 'Sprint 1',
                'status' => SprintStatus::Planned->value,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(13)->toDateString(),
                'capacity_hours' => 120,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('sprint.name', 'Sprint 1');

        $this->assertDatabaseHas('sprints', [
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'created_by' => $user->id,
            'capacity_hours' => 120,
        ]);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->sprintRoute($user, 'projects.sprints.store', $project), [
                'name' => 'Backwards sprint',
                'status' => SprintStatus::Planned->value,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->subDay()->toDateString(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['ends_on']);
    }

    public function test_a_developer_without_manage_rights_cannot_create_a_sprint(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($developer)
            ->postJson($this->sprintRoute($owner, 'projects.sprints.store', $project, team: $team), [
                'name' => 'Should fail',
                'status' => SprintStatus::Planned->value,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(13)->toDateString(),
            ]);

        $response->assertForbidden();
    }

    public function test_a_sprint_can_be_updated(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $sprint = Sprint::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->sprintRoute($user, 'projects.sprints.update', $project, $sprint), [
                'name' => 'Renamed sprint',
                'status' => SprintStatus::Active->value,
                'starts_on' => $sprint->starts_on->toDateString(),
                'ends_on' => $sprint->ends_on->toDateString(),
                'committed_hours' => 80,
            ]);

        $response->assertOk();
        $response->assertJsonPath('sprint.name', 'Renamed sprint');
        $response->assertJsonPath('sprint.status', SprintStatus::Active->value);
    }

    public function test_a_sprint_cannot_be_reached_through_another_projects_url(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $sprint = Sprint::factory()->for($otherProject)->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->sprintRoute($user, 'projects.sprints.destroy', $project, $sprint));

        $response->assertNotFound();
    }

    public function test_a_sprint_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $sprint = Sprint::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->sprintRoute($user, 'projects.sprints.destroy', $project, $sprint));

        $response->assertOk();
        $this->assertSoftDeleted($sprint);
    }

    private function sprintRoute(User $user, string $name, Project $project, ?Sprint $sprint = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'sprint' => $sprint,
        ]));
    }
}

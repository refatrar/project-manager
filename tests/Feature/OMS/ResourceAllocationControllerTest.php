<?php

namespace Tests\Feature\OMS;

use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAllocationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_manager_can_book_a_members_hours(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->route($owner, 'projects.resource-allocations.store', $project), [
                'user_id' => $owner->id,
                'status' => 'planned',
                'starts_on' => '2026-05-01',
                'ends_on' => '2026-05-05',
                'hours_per_day' => 6,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('resource_allocations', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'hours_per_day' => 6,
        ]);
    }

    public function test_booking_a_non_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->route($owner, 'projects.resource-allocations.store', $project), [
                'user_id' => $outsider->id,
                'status' => 'planned',
                'starts_on' => '2026-05-01',
                'ends_on' => '2026-05-05',
                'hours_per_day' => 6,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_a_non_manager_cannot_book_hours(): void
    {
        $owner = User::factory()->create();
        $developer = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $developer->id, 'role' => 'developer']);
        $owner->currentTeam->members()->attach($developer, ['role' => 'member']);

        $response = $this
            ->actingAs($developer)
            ->postJson($this->route($developer, 'projects.resource-allocations.store', $project, null, $owner->currentTeam), [
                'user_id' => $developer->id,
                'status' => 'planned',
                'starts_on' => '2026-05-01',
                'ends_on' => '2026-05-05',
                'hours_per_day' => 6,
            ]);

        $response->assertForbidden();
    }

    public function test_a_manager_can_update_a_booking(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $allocation = ResourceAllocation::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->putJson($this->route($owner, 'projects.resource-allocations.update', $project, $allocation), [
                'user_id' => $owner->id,
                'status' => 'confirmed',
                'starts_on' => $allocation->starts_on->toDateString(),
                'ends_on' => $allocation->ends_on->toDateString(),
                'hours_per_day' => 4,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('resource_allocations', ['id' => $allocation->id, 'status' => 'confirmed', 'hours_per_day' => 4]);
    }

    public function test_a_manager_can_delete_a_booking(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $allocation = ResourceAllocation::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->route($owner, 'projects.resource-allocations.destroy', $project, $allocation));

        $response->assertOk();
        $this->assertDatabaseMissing('resource_allocations', ['id' => $allocation->id]);
    }

    public function test_a_project_from_another_team_cannot_be_reached_through_the_users_own_team_url(): void
    {
        $user = User::factory()->create();
        $foreignProject = Project::factory()->create();
        ProjectMember::factory()->create(['project_id' => $foreignProject->id, 'user_id' => $user->id, 'role' => 'owner']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'projects.resource-allocations.store', $foreignProject), [
                'user_id' => $user->id,
                'status' => 'planned',
                'starts_on' => '2026-05-01',
                'ends_on' => '2026-05-05',
                'hours_per_day' => 6,
            ]);

        $response->assertNotFound();
    }

    /**
     * @param  'projects.resource-allocations.store'|'projects.resource-allocations.update'|'projects.resource-allocations.destroy'  $name
     */
    private function route(User $user, string $name, Project $project, ?ResourceAllocation $allocation = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'project' => $project,
            'allocation' => $allocation,
        ]));
    }
}

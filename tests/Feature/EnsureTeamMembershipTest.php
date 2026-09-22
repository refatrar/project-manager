<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTeamMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_reach_a_team_scoped_route(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]));

        $response->assertOk();
    }

    public function test_a_user_with_no_membership_on_the_team_is_forbidden(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard', ['current_team' => $otherTeam->slug]));

        $response->assertForbidden();
    }

    public function test_an_unauthenticated_request_is_redirected_to_login(): void
    {
        $team = Team::factory()->create();

        $response = $this->get(route('dashboard', ['current_team' => $team->slug]));

        $response->assertRedirect(route('login'));
    }

    public function test_a_nonexistent_team_slug_404s_rather_than_erroring(): void
    {
        $user = User::factory()->create();

        // 404, not 403: every controller in this app type-hints
        // `Team $current_team`, so Laravel's own implicit route-model
        // binding resolves (or fails to resolve) the slug before
        // `EnsureTeamMembership` ever runs — a slug matching no team
        // never reaches the middleware's own membership check at all.
        $response = $this
            ->actingAs($user)
            ->get(route('dashboard', ['current_team' => 'does-not-exist']));

        $response->assertNotFound();
    }

    public function test_visiting_a_team_scoped_route_switches_the_users_current_team(): void
    {
        $user = User::factory()->create();
        $secondTeam = Team::factory()->create();
        $secondTeam->memberships()->create(['user_id' => $user->id, 'role' => TeamRole::Owner]);

        $this->assertFalse($user->isCurrentTeam($secondTeam));

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['current_team' => $secondTeam->slug]))
            ->assertOk();

        $this->assertTrue($user->fresh()->isCurrentTeam($secondTeam));
    }

    public function test_the_non_current_team_route_parameter_is_also_recognized(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.edit', ['team' => $user->currentTeam->slug]));

        $response->assertOk();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.edit', ['team' => $otherTeam->slug]));

        $response->assertForbidden();
    }
}

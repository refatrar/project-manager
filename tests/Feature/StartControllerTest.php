<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_a_team_is_redirected_to_their_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('start'));

        $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    }

    public function test_a_teamless_user_sees_the_holding_page(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();
        $user->teams()->detach();
        // `switchTeam()` (called by the factory's own `afterCreating` hook)
        // cached the `currentTeam` relation on this in-memory instance —
        // updating the column alone doesn't clear it.
        $user->refresh();

        $response = $this->actingAs($user)->get(route('start'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('no-team'));
    }

    public function test_the_holding_page_shows_pending_invitations(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();
        $user->teams()->detach();
        $user->refresh();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Invited Team']);
        $team->members()->attach($owner, ['role' => TeamRole::TeamLead->value]);
        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => $user->email,
            'invited_by' => $owner->id,
        ]);

        $response = $this->actingAs($user)->get(route('start'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('no-team')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code));
    }
}

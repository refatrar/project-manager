<?php

namespace Tests\Feature\Teams;

use App\Actions\Teams\AssignTeamLeader;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignTeamLeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_the_owner_role_to_a_teamless_user_and_switches_them_to_the_team(): void
    {
        $team = Team::factory()->create();
        // The factory's own `afterCreating` hook gives every user a
        // personal team and switches to it — set `current_team_id` back to
        // null afterward, rather than passing it as a create override
        // (the hook runs after `create()` and would just overwrite it).
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();

        app(AssignTeamLeader::class)->handle($team, $user);

        $this->assertSame(TeamRole::TeamLead, $team->memberships()->where('user_id', $user->id)->first()->role);
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_it_does_not_switch_a_user_who_already_has_a_current_team(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $originalTeamId = $user->current_team_id;

        app(AssignTeamLeader::class)->handle($team, $user);

        $this->assertSame($originalTeamId, $user->fresh()->current_team_id);
    }

    public function test_reassigning_a_leader_demotes_the_previous_one_to_member(): void
    {
        $team = Team::factory()->create();
        $firstLeader = User::factory()->create();
        $secondLeader = User::factory()->create();

        app(AssignTeamLeader::class)->handle($team, $firstLeader);
        app(AssignTeamLeader::class)->handle($team, $secondLeader);

        $this->assertSame(TeamRole::Member, $team->memberships()->where('user_id', $firstLeader->id)->first()->role);
        $this->assertSame(TeamRole::TeamLead, $team->memberships()->where('user_id', $secondLeader->id)->first()->role);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Actions\Teams\AssignTeamLeader;
use App\Enums\TeamRole;
use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_an_admin_with_permission_can_create_a_team_with_no_members(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.store'), ['name' => 'Newly Provisioned Team']);

        $response->assertOk();
        $team = Team::query()->where('name', 'Newly Provisioned Team')->firstOrFail();
        $this->assertSame(0, $team->members()->count());
    }

    public function test_an_admin_can_assign_an_existing_user_as_team_leader(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.assign-leader', ['team' => $team->slug]), ['email' => $user->email]);

        $response->assertOk();
        $this->assertSame(TeamRole::TeamLead, $team->fresh()->memberships()->where('user_id', $user->id)->first()->role);
    }

    public function test_assigning_a_leader_by_an_unknown_email_fails_validation(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.assign-leader', ['team' => $team->slug]), ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, $team->members()->count());
    }

    public function test_an_admin_can_rename_a_team(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create(['name' => 'Original Name']);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.teams.update', ['team' => $team->slug]), ['name' => 'Renamed Team']);

        $response->assertOk();
        $team->refresh();
        $this->assertSame('Renamed Team', $team->name);
        $this->assertSame('renamed-team', $team->slug);
    }

    public function test_an_admin_can_remove_a_team_leader_and_leave_the_team_leaderless(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();
        $user = User::factory()->create();
        app(AssignTeamLeader::class)->handle($team, $user);
        $user->refresh();

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.teams.remove-leader', ['team' => $team->slug]));

        $response->assertOk();
        $membership = $team->fresh()->memberships()->where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(TeamRole::Member, $membership->role);
        $this->assertNull($team->fresh()->owner());
        $this->assertSame($user->current_team_id, $user->fresh()->current_team_id);
    }

    public function test_a_regular_user_cannot_access_the_admin_teams_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.teams.index'));

        $response->assertRedirect(route('admin.login'));
    }
}

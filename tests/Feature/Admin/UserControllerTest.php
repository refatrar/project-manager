<?php

namespace Tests\Feature\Admin;

use App\Actions\Teams\AssignTeamLeader;
use App\Enums\TeamRole;
use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_an_admin_can_list_registered_users(): void
    {
        $user = User::factory()->create(['name' => 'Registered Person']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('users.data.0.name', $user->name)
                ->has('teams')
                ->has('roles'));
    }

    public function test_an_admin_can_create_a_user_without_a_team(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'New Person',
                'email' => 'new-person@example.com',
                'password' => 'password',
            ])
            ->assertOk();

        $user = User::query()->where('email', 'new-person@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(0, $user->teamMemberships()->count());
        $this->assertNull($user->current_team_id);
    }

    public function test_an_admin_can_update_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.users.update', $user), [
                'name' => 'Renamed Person',
                'email' => 'renamed@example.com',
                'password' => 'new-password',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'Someone',
                'email' => 'taken@example.com',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_an_admin_can_delete_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($user);
    }

    public function test_an_admin_can_assign_a_user_to_a_team(): void
    {
        $user = User::factory()->create();
        $user->update(['current_team_id' => null]);
        $team = Team::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.users.teams.store', $user), [
                'team_id' => $team->id,
                'role' => TeamRole::Member->value,
            ])
            ->assertOk();

        $membership = $team->memberships()->where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(TeamRole::Member, $membership->role);
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_assigning_the_owner_role_makes_that_user_the_team_leader(): void
    {
        $team = Team::factory()->create();
        $previous = User::factory()->create();
        app(AssignTeamLeader::class)->handle($team, $previous);
        $next = User::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.users.teams.store', $next), [
                'team_id' => $team->id,
                'role' => TeamRole::Owner->value,
            ])
            ->assertOk();

        $this->assertSame(
            TeamRole::Owner,
            $team->memberships()->where('user_id', $next->id)->firstOrFail()->role,
        );
        $this->assertSame(
            TeamRole::Member,
            $team->memberships()->where('user_id', $previous->id)->firstOrFail()->role,
        );
    }

    public function test_an_admin_can_remove_a_user_from_a_team(): void
    {
        $user = User::factory()->create();
        $current = $user->currentTeam;
        $this->assertNotNull($current);
        $other = Team::factory()->create();
        $other->memberships()->create([
            'user_id' => $user->id,
            'role' => TeamRole::Member->value,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.users.teams.destroy', [$user, $current]))
            ->assertOk();

        $this->assertFalse($user->fresh()->belongsToTeam($current));
        $this->assertSame($other->id, $user->fresh()->current_team_id);
    }

    public function test_a_team_account_cannot_open_the_users_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('admin.login'));
    }
}

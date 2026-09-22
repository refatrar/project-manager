<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\TeamRole;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithManageTeams(): Admin
    {
        $permission = Permission::factory()->create(['key' => AdminPermission::ManageTeams->value]);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);

        $admin = Admin::factory()->create();
        $admin->roles()->attach($role);

        return $admin;
    }

    public function test_an_admin_with_permission_can_create_a_team_with_no_members(): void
    {
        $admin = $this->adminWithManageTeams();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.store'), ['name' => 'Newly Provisioned Team']);

        $response->assertOk();
        $team = Team::query()->where('name', 'Newly Provisioned Team')->firstOrFail();
        $this->assertSame(0, $team->members()->count());
    }

    public function test_an_admin_without_permission_cannot_create_a_team(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.store'), ['name' => 'Should Not Exist']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teams', ['name' => 'Should Not Exist']);
    }

    public function test_an_admin_can_assign_an_existing_user_as_team_leader(): void
    {
        $admin = $this->adminWithManageTeams();
        $team = Team::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.assign-leader', ['team' => $team->slug]), ['email' => $user->email]);

        $response->assertOk();
        $this->assertSame(TeamRole::Owner, $team->fresh()->memberships()->where('user_id', $user->id)->first()->role);
    }

    public function test_assigning_a_leader_by_an_unknown_email_fails_validation(): void
    {
        $admin = $this->adminWithManageTeams();
        $team = Team::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.teams.assign-leader', ['team' => $team->slug]), ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, $team->members()->count());
    }

    public function test_a_regular_user_cannot_access_the_admin_teams_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.teams.index'));

        $response->assertRedirect(route('admin.login'));
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamModulePermission;
use App\Enums\TeamRole;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_the_catalogue_is_global_and_owner_starts_with_every_team_permission(): void
    {
        app(TeamAccessControl::class)->ensureCatalogue();

        $this->assertDatabaseHas('permissions', [
            'name' => TeamModulePermission::ViewProjects->value,
            'guard_name' => 'web',
            'module' => 'Projects',
        ]);

        $owner = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'owner')->first();
        $this->assertNotNull($owner);
        $this->assertSame('Project Lead', $owner->name);
        $this->assertSame('Project Lead', TeamRole::Owner->label());
        $adminRole = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);
        $this->assertSame('Team Lead', $adminRole->name);
        $this->assertSame('Team Lead', TeamRole::Admin->label());
        $this->assertSame(count(TeamModulePermission::cases()), $owner->permissions()->count());
        $this->assertSame(1, Role::query()->where('guard_name', 'web')->where('slug', 'owner')->count());
    }

    public function test_an_admin_can_create_a_role_and_a_team_account_uses_it(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        app(TeamAccessControl::class)->ensureCatalogue();

        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', TeamModulePermission::ViewProjects->value)
            ->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.team-roles.store'), [
            'name' => 'Reviewer',
            'description' => 'Can look at projects.',
            'permissions' => [$permission->id],
        ])->assertOk();

        $this->actingAs($owner, 'web')->patch(route('teams.members.update', [$team, $member]), [
            'role' => 'reviewer',
        ])->assertRedirect();

        $this->assertTrue($member->fresh()->teamCan($team, TeamModulePermission::ViewProjects));
        $this->assertFalse($member->fresh()->teamCan($team, TeamModulePermission::CreateProjects));
    }

    public function test_changing_a_role_in_the_admin_panel_changes_every_team_that_uses_it(): void
    {
        $admin = $this->admin();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstAdmin = User::factory()->create();
        $secondAdmin = User::factory()->create();
        $firstTeam = $firstOwner->currentTeam;
        $secondTeam = $secondOwner->currentTeam;
        $firstTeam->members()->attach($firstAdmin, ['role' => TeamRole::Admin->value]);
        $secondTeam->members()->attach($secondAdmin, ['role' => TeamRole::Admin->value]);

        app(TeamAccessControl::class)->ensureCatalogue();

        $adminRole = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'admin')->firstOrFail();
        $kept = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', TeamModulePermission::ViewAvailability->value)
            ->pluck('id')
            ->all();

        $this->actingAs($admin, 'admin')->patch(route('admin.team-roles.update', $adminRole), [
            'name' => 'Admin',
            'permissions' => $kept,
        ])->assertOk();

        $this->assertFalse($firstAdmin->fresh()->teamCan($firstTeam, TeamModulePermission::ViewAvailability));
        $this->assertFalse($secondAdmin->fresh()->teamCan($secondTeam, TeamModulePermission::ViewAvailability));
        $this->assertTrue($firstAdmin->fresh()->teamCan($firstTeam, TeamModulePermission::ViewProjects));
    }

    public function test_any_signed_in_admin_can_open_team_roles(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.team-roles.index'))
            ->assertOk();
    }

    public function test_a_built_in_role_cannot_be_deleted_and_an_admin_panel_role_is_hidden(): void
    {
        $admin = $this->admin();
        app(TeamAccessControl::class)->ensureCatalogue();

        $ownerRole = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'owner')->firstOrFail();

        $this->actingAs($admin, 'admin')->delete(route('admin.team-roles.destroy', $ownerRole))
            ->assertForbidden();

        $platformRole = Role::factory()->create(['guard_name' => 'admin', 'slug' => 'platform-only']);

        $this->actingAs($admin, 'admin')->patch(route('admin.team-roles.update', $platformRole), [
            'name' => 'Hijacked',
            'permissions' => [],
        ])->assertNotFound();
    }
}

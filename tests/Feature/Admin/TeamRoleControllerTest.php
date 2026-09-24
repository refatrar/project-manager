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

    public function test_the_catalogue_is_global_and_only_team_lead_and_member_are_built_in(): void
    {
        app(TeamAccessControl::class)->ensureCatalogue();

        $this->assertDatabaseHas('permissions', [
            'name' => TeamModulePermission::ViewProjects->value,
            'guard_name' => 'web',
            'module' => 'Projects',
        ]);

        $teamLead = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'team_lead')->first();
        $this->assertNotNull($teamLead);
        $this->assertSame('Team Lead', $teamLead->name);
        $this->assertSame('Team Lead', TeamRole::TeamLead->label());
        $this->assertSame(count(TeamModulePermission::cases()), $teamLead->permissions()->count());
        $this->assertSame(1, Role::query()->where('guard_name', 'web')->where('slug', 'team_lead')->count());

        $this->assertDatabaseMissing('roles', ['guard_name' => 'web', 'team_id' => null, 'slug' => 'owner']);
        $this->assertDatabaseMissing('roles', ['guard_name' => 'web', 'team_id' => null, 'slug' => 'admin']);
    }

    public function test_an_admin_can_create_a_role_and_a_team_account_uses_it(): void
    {
        $admin = $this->admin();
        $teamLead = User::factory()->create();
        $member = User::factory()->create();
        $team = $teamLead->currentTeam;
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

        $this->actingAs($teamLead, 'web')->patch(route('teams.members.update', [$team, $member]), [
            'role' => 'reviewer',
        ])->assertRedirect();

        $this->assertTrue($member->fresh()->teamCan($team, TeamModulePermission::ViewProjects));
        $this->assertFalse($member->fresh()->teamCan($team, TeamModulePermission::CreateProjects));
    }

    public function test_changing_a_custom_role_in_the_admin_panel_changes_every_team_that_uses_it(): void
    {
        $admin = $this->admin();
        $firstTeamLead = User::factory()->create();
        $secondTeamLead = User::factory()->create();
        $firstReviewer = User::factory()->create();
        $secondReviewer = User::factory()->create();
        $firstTeam = $firstTeamLead->currentTeam;
        $secondTeam = $secondTeamLead->currentTeam;

        app(TeamAccessControl::class)->ensureCatalogue();

        $viewProjects = Permission::query()->where('guard_name', 'web')->where('name', TeamModulePermission::ViewProjects->value)->firstOrFail();
        $viewAvailability = Permission::query()->where('guard_name', 'web')->where('name', TeamModulePermission::ViewAvailability->value)->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.team-roles.store'), [
            'name' => 'Reviewer',
            'description' => 'Can look at projects.',
            'permissions' => [$viewProjects->id, $viewAvailability->id],
        ])->assertOk();

        $reviewerRole = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'reviewer')->firstOrFail();

        $firstTeam->members()->attach($firstReviewer, ['role' => 'reviewer']);
        $secondTeam->members()->attach($secondReviewer, ['role' => 'reviewer']);

        $this->actingAs($admin, 'admin')->patch(route('admin.team-roles.update', $reviewerRole), [
            'name' => 'Reviewer',
            'permissions' => [$viewProjects->id],
        ])->assertOk();

        $this->assertFalse($firstReviewer->fresh()->teamCan($firstTeam, TeamModulePermission::ViewAvailability));
        $this->assertFalse($secondReviewer->fresh()->teamCan($secondTeam, TeamModulePermission::ViewAvailability));
        $this->assertTrue($firstReviewer->fresh()->teamCan($firstTeam, TeamModulePermission::ViewProjects));
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

        $teamLeadRole = Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', 'team_lead')->firstOrFail();

        $this->actingAs($admin, 'admin')->delete(route('admin.team-roles.destroy', $teamLeadRole))
            ->assertForbidden();

        $platformRole = Role::factory()->create(['guard_name' => 'admin', 'slug' => 'platform-only']);

        $this->actingAs($admin, 'admin')->patch(route('admin.team-roles.update', $platformRole), [
            'name' => 'Hijacked',
            'permissions' => [],
        ])->assertNotFound();
    }
}

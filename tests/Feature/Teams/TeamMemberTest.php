<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomRole(string $slug, string $name): Role
    {
        app(TeamAccessControl::class)->ensureCatalogue();

        return Role::query()->create([
            'team_id' => null,
            'guard_name' => 'web',
            'slug' => $slug,
            'name' => $name,
            'is_system' => false,
        ]);
    }

    public function test_team_member_roles_can_be_updated_by_team_leads()
    {
        $teamLead = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $this->createCustomRole('reviewer', 'Reviewer');

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($teamLead)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => 'reviewer',
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertEquals(
            'reviewer',
            $team->members()->where('user_id', $member->id)->first()->pivot->roleSlug(),
        );
    }

    public function test_team_member_roles_cannot_be_updated_by_non_team_leads()
    {
        $teamLead = User::factory()->create();
        $otherMember = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $this->createCustomRole('reviewer', 'Reviewer');

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($otherMember)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => 'reviewer',
            ]);

        $response->assertForbidden();
    }

    public function test_team_members_can_be_removed_by_team_leads()
    {
        $teamLead = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($teamLead)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_team_members_cannot_be_removed_by_non_team_leads()
    {
        $teamLead = User::factory()->create();
        $otherMember = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($otherMember)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $response->assertForbidden();
    }

    public function test_team_lead_cannot_be_removed()
    {
        $teamLead = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);

        $response = $this
            ->actingAs($teamLead)
            ->delete(route('teams.members.destroy', [$team, $teamLead]));

        $response->assertForbidden();

        $this->assertTrue($teamLead->fresh()->belongsToTeam($team));
    }

    public function test_team_member_role_cannot_be_set_to_team_lead()
    {
        $teamLead = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($teamLead)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::TeamLead->value,
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertEquals(
            TeamRole::Member->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_removed_member_current_team_is_set_to_personal_team()
    {
        $teamLead = User::factory()->create();
        $member = User::factory()->create();
        $personalTeam = $member->personalTeam();
        $team = Team::factory()->create();

        $team->members()->attach($teamLead, ['role' => TeamRole::TeamLead->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($teamLead)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertEquals($personalTeam->id, $member->fresh()->current_team_id);
    }
}

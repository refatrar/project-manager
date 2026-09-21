<?php

namespace Tests\Feature\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_owner_can_add_a_team_member_to_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->memberRoute($owner, 'projects.members.store', $project), [
                'user_id' => $developer->id,
                'role' => ProjectMemberRole::Developer->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 80,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('member.user.id', $developer->id);
        $response->assertJsonPath('member.allocation_percentage', 80);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectMemberRole::Developer->value,
        ]);
    }

    public function test_a_user_outside_the_team_cannot_be_added(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $outsider = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->memberRoute($owner, 'projects.members.store', $project), [
                'user_id' => $outsider->id,
                'role' => ProjectMemberRole::Developer->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 100,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_a_user_already_an_active_member_cannot_be_added_again(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->memberRoute($owner, 'projects.members.store', $project), [
                'user_id' => $developer->id,
                'role' => ProjectMemberRole::Developer->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 100,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_re_adding_a_removed_member_restores_the_soft_deleted_row_instead_of_duplicating(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        $original = ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $original->deleted_by = $owner->id;
        $original->save();
        $original->delete();

        $response = $this
            ->actingAs($owner)
            ->postJson($this->memberRoute($owner, 'projects.members.store', $project), [
                'user_id' => $developer->id,
                'role' => ProjectMemberRole::Manager->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 50,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('member.id', $original->id);

        $this->assertDatabaseCount('project_members', 1);
        $this->assertDatabaseHas('project_members', [
            'id' => $original->id,
            'deleted_at' => null,
            'role' => ProjectMemberRole::Manager->value,
            'allocation_percentage' => 50,
        ]);
    }

    public function test_a_developer_without_manage_rights_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);
        $another = User::factory()->create();
        $team->members()->attach($another, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($developer)
            ->postJson($this->memberRoute($owner, 'projects.members.store', $project, team: $team), [
                'user_id' => $another->id,
                'role' => ProjectMemberRole::Developer->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 100,
            ]);

        $response->assertForbidden();
    }

    public function test_a_project_manager_can_update_a_members_role_and_allocation(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $member = ProjectMember::factory()->for($project)->create(['allocation_percentage' => 100]);

        $response = $this
            ->actingAs($owner)
            ->putJson($this->memberRoute($owner, 'projects.members.update', $project, $member), [
                'role' => ProjectMemberRole::Lead->value,
                'status' => ProjectMemberStatus::Active->value,
                'allocation_percentage' => 60,
            ]);

        $response->assertOk();
        $response->assertJsonPath('member.role', ProjectMemberRole::Lead->value);
        $response->assertJsonPath('member.allocation_percentage', 60);
    }

    public function test_a_member_can_be_removed_and_left_on_is_stamped(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $member = ProjectMember::factory()->for($project)->create();

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->memberRoute($owner, 'projects.members.destroy', $project, $member));

        $response->assertOk();
        $this->assertSoftDeleted($member);
        $this->assertNotNull($member->fresh()->left_on);
    }

    public function test_a_member_cannot_be_reached_through_another_projects_url(): void
    {
        $owner = User::factory()->create();
        $otherProject = Project::factory()->for($owner->currentTeam)->create();
        $member = ProjectMember::factory()->for($otherProject)->create();
        $project = Project::factory()->for($owner->currentTeam)->create();

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->memberRoute($owner, 'projects.members.destroy', $project, $member));

        $response->assertNotFound();
    }

    private function memberRoute(User $user, string $name, Project $project, ?ProjectMember $member = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'member' => $member,
        ]));
    }
}

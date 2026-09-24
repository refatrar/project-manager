<?php

namespace Tests\Feature\OMS;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->get($this->projectsRoute($user, 'projects.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_team_admin_sees_every_project_without_being_a_member(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        Project::factory()->for($team)->create();
        Project::factory()->for($team)->create();

        $response = $this
            ->actingAs($user)
            ->get($this->projectsRoute($user, 'projects.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects.data', 2)
            ->where('projects.data.0.can_update', true)
            ->where('projects.data.1.can_update', true),
        );
    }

    public function test_a_plain_member_only_sees_projects_they_belong_to(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $visible = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($visible)->create(['user_id' => $member->id]);
        Project::factory()->for($team)->create();

        $response = $this
            ->actingAs($member)
            ->get($this->projectsRoute($owner, 'projects.index', team: $team));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $visible->id)
            ->where('projects.data.0.can_update', false),
        );
    }

    public function test_a_team_member_cannot_see_another_teams_projects(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        Project::factory()->for($otherTeam)->create();

        $response = $this
            ->actingAs($user)
            ->get($this->projectsRoute($user, 'projects.index'));

        $response->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    }

    public function test_valid_payload_creates_a_project_and_adds_the_creator_as_owner(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Alpha Platform',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('project.code', 'ALPHA');
        $response->assertJsonPath('project.slug', 'alpha-platform');

        $this->assertDatabaseHas('projects', [
            'code' => 'ALPHA',
            'name' => 'Alpha Platform',
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $project = Project::where('code', 'ALPHA')->firstOrFail();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectMemberRole::Owner->value,
        ]);
    }

    public function test_empty_payload_rejects_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code', 'name', 'status', 'priority', 'health']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_duplicate_code_within_the_same_team_is_rejected(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user->currentTeam)->create(['code' => 'ALPHA']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Second Alpha',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::Medium->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_the_same_code_is_allowed_on_a_different_team(): void
    {
        $user = User::factory()->create();
        Project::factory()->for(Team::factory())->create(['code' => 'ALPHA']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'Alpha Platform',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::Medium->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertCreated();
    }

    public function test_a_soft_deleted_projects_code_remains_reserved(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create(['code' => 'ALPHA']);
        $project->delete();

        $response = $this
            ->actingAs($user)
            ->postJson($this->projectsRoute($user, 'projects.store'), [
                'code' => 'ALPHA',
                'name' => 'New Alpha',
                'status' => ProjectStatus::Planning->value,
                'priority' => Priority::Medium->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_a_member_without_project_access_cannot_view_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $outsider = User::factory()->create();
        $team->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();

        $response = $this
            ->actingAs($outsider)
            ->get($this->projectsRoute($owner, 'projects.show', team: $team, project: $project));

        $response->assertForbidden();
    }

    public function test_a_project_cannot_be_reached_through_another_teams_url(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $project = Project::factory()->for($otherTeam)->create();

        $response = $this
            ->actingAs($user)
            ->get($this->projectsRoute($user, 'projects.show', project: $project));

        $response->assertNotFound();
    }

    public function test_a_developer_cannot_update_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($developer)
            ->putJson($this->projectsRoute($owner, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Renamed',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::Medium->value,
                'health' => ProjectHealth::OnTrack->value,
            ]);

        $response->assertForbidden();
    }

    public function test_a_project_manager_can_update_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = User::factory()->create();
        $team->members()->attach($manager, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->manager()->create(['user_id' => $manager->id]);

        $response = $this
            ->actingAs($manager)
            ->putJson($this->projectsRoute($owner, 'projects.update', team: $team, project: $project), [
                'code' => $project->code,
                'name' => 'Renamed Project',
                'status' => ProjectStatus::Active->value,
                'priority' => Priority::High->value,
                'health' => ProjectHealth::AtRisk->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('project.name', 'Renamed Project');
    }

    public function test_archiving_a_project_stamps_archived_at_and_excludes_it_from_the_open_scope(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->patchJson($this->projectsRoute($user, 'projects.archive', project: $project));

        $response->assertOk();
        $response->assertJsonPath('project.status', ProjectStatus::Archived->value);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Archived->value,
        ]);
        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertSame([], Project::open()->pluck('id')->all());
    }

    public function test_a_project_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->projectsRoute($user, 'projects.destroy', project: $project));

        $response->assertOk();
        $this->assertSoftDeleted($project);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_by' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function projectsRoute(User $user, string $name, ?Team $team = null, ?Project $project = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
        ]));
    }
}

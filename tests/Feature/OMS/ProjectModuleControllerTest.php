<?php

namespace Tests\Feature\OMS;

use App\Enums\Priority;
use App\Enums\ProjectModuleStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_owner_can_create_a_root_module(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->moduleRoute($user, 'projects.modules.store', $project), [
                'name' => 'Onboarding',
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('module.name', 'Onboarding');
        $response->assertJsonPath('module.parent_id', null);

        $this->assertDatabaseHas('project_modules', [
            'project_id' => $project->id,
            'name' => 'Onboarding',
            'created_by' => $user->id,
        ]);
    }

    public function test_position_increments_among_siblings(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        ProjectModule::factory()->for($project)->create(['position' => 0]);
        ProjectModule::factory()->for($project)->create(['position' => 1]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->moduleRoute($user, 'projects.modules.store', $project), [
                'name' => 'Third module',
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('module.position', 2);
    }

    public function test_a_developer_without_manage_rights_cannot_create_a_module(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $developer = User::factory()->create();
        $team->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $developer->id]);

        $response = $this
            ->actingAs($developer)
            ->postJson($this->moduleRoute($owner, 'projects.modules.store', $project, team: $team), [
                'name' => 'Should fail',
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertForbidden();
    }

    public function test_a_module_cannot_be_reached_through_another_projects_url(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $module = ProjectModule::factory()->for($otherProject)->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->moduleRoute($user, 'projects.modules.update', $project, $module), [
                'name' => 'Hijacked',
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertNotFound();
    }

    public function test_a_module_cannot_be_set_as_its_own_parent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $module = ProjectModule::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->moduleRoute($user, 'projects.modules.update', $project, $module), [
                'parent_id' => $module->id,
                'name' => $module->name,
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['parent_id']);
    }

    public function test_a_module_cannot_be_moved_under_its_own_descendant(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $grandparent = ProjectModule::factory()->for($project)->create();
        $child = ProjectModule::factory()->for($project)->childOf($grandparent)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->moduleRoute($user, 'projects.modules.update', $project, $grandparent), [
                'parent_id' => $child->id,
                'name' => $grandparent->name,
                'status' => ProjectModuleStatus::Planning->value,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertUnprocessable();
    }

    public function test_reordering_applies_new_positions_and_parents(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $first = ProjectModule::factory()->for($project)->create(['position' => 0]);
        $second = ProjectModule::factory()->for($project)->create(['position' => 1]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->moduleRoute($user, 'projects.modules.reorder', $project), [
                'modules' => [
                    ['id' => $first->id, 'parent_id' => $second->id, 'position' => 0],
                    ['id' => $second->id, 'parent_id' => null, 'position' => 0],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('project_modules', [
            'id' => $first->id,
            'parent_id' => $second->id,
            'position' => 0,
        ]);
    }

    public function test_reordering_rejects_a_cycle(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $parent = ProjectModule::factory()->for($project)->create();
        $child = ProjectModule::factory()->for($project)->childOf($parent)->create();

        $response = $this
            ->actingAs($user)
            ->patchJson($this->moduleRoute($user, 'projects.modules.reorder', $project), [
                'modules' => [
                    ['id' => $parent->id, 'parent_id' => $child->id, 'position' => 0],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_a_module_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $module = ProjectModule::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->moduleRoute($user, 'projects.modules.destroy', $project, $module));

        $response->assertOk();
        $this->assertSoftDeleted($module);
    }

    private function moduleRoute(User $user, string $name, Project $project, ?ProjectModule $module = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'module' => $module,
        ]));
    }
}

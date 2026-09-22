<?php

namespace Tests\Feature\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateProjectMemberCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_manager_sees_member_capacity_scoped_to_this_project_only(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        UserWorkSchedule::factory()->create([
            'user_id' => $member->id,
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2020-01-01',
        ]);

        $projectA = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $projectB = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $projectA->id, 'user_id' => $owner->id, 'role' => 'owner']);
        ProjectMember::factory()->create(['project_id' => $projectA->id, 'user_id' => $member->id, 'role' => 'developer']);
        ProjectMember::factory()->create(['project_id' => $projectB->id, 'user_id' => $member->id, 'role' => 'developer']);

        // A booking on the OTHER project must never reduce the figure shown
        // for project A's manager.
        ResourceAllocation::factory()->create([
            'user_id' => $member->id,
            'project_id' => $projectB->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(13)->toDateString(),
            'hours_per_day' => 8,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('projects.show', ['current_team' => $owner->currentTeam->slug, 'project' => $projectA]));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($member) {
            $page->component('projects/show');
            $rows = collect($page->toArray()['props']['memberCapacity']);
            $entry = $rows->firstWhere('user_id', $member->id);
            $this->assertNotNull($entry);
            // Nothing from project B's booking leaked in: full capacity
            // remains available from project A's point of view.
            $this->assertGreaterThan(0, $entry['available_hours_14d']);
        });
    }

    public function test_a_plain_team_member_who_only_holds_a_non_managing_project_role_sees_no_capacity_data(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $owner->currentTeam->members()->attach($viewer, ['role' => TeamRole::Member->value]);

        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $viewer->id, 'role' => ProjectMemberRole::Developer]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('projects.show', ['current_team' => $owner->currentTeam->slug, 'project' => $project]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/show')
            ->where('memberCapacity', []));
    }
}

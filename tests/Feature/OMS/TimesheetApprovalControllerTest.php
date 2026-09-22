<?php

namespace Tests\Feature\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_manager_sees_submitted_entries_for_their_project(): void
    {
        $manager = User::factory()->create();
        $developer = User::factory()->create();
        $manager->currentTeam->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $manager->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager']);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $developer->id,
            'team_id' => $manager->currentTeam->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this
            ->actingAs($manager)
            ->get(route('timesheet-approvals.index', ['current_team' => $manager->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('timesheet-approvals/index')
            ->has('entries', 1)
            ->where('entries.0.id', $timeLog->id));
    }

    public function test_a_non_manager_does_not_see_submitted_entries_for_that_project(): void
    {
        $owner = User::factory()->create();
        $developer = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->currentTeam->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $owner->currentTeam->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $developer->id, 'role' => 'developer']);
        TimeLog::factory()->create([
            'user_id' => $developer->id,
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this
            ->actingAs($outsider)
            ->get(route('timesheet-approvals.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('timesheet-approvals/index')
            ->has('entries', 0));
    }

    public function test_a_team_admin_sees_every_submitted_entry_including_project_less_ones(): void
    {
        $owner = User::factory()->create();
        $developer = User::factory()->create();
        $owner->currentTeam->members()->attach($developer, ['role' => TeamRole::Member->value]);
        TimeLog::factory()->create([
            'user_id' => $developer->id,
            'team_id' => $owner->currentTeam->id,
            'project_id' => null,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('timesheet-approvals.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('timesheet-approvals/index')
            ->has('entries', 1));
    }

    public function test_a_project_manager_can_approve_an_entry(): void
    {
        $manager = User::factory()->create();
        $developer = User::factory()->create();
        $manager->currentTeam->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $manager->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager']);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $developer->id,
            'team_id' => $manager->currentTeam->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this
            ->actingAs($manager)
            ->patchJson($this->decideRoute($manager, $timeLog), ['decision' => 'approved']);

        $response->assertOk();
        $timeLog->refresh();
        $this->assertSame(ApprovalStatus::Approved, $timeLog->approval_status);
        $this->assertSame($manager->id, $timeLog->approved_by);
    }

    public function test_a_developer_on_the_project_cannot_approve_an_entry(): void
    {
        $owner = User::factory()->create();
        $developer = User::factory()->create();
        $owner->currentTeam->members()->attach($developer, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $developer->id, 'role' => 'developer']);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $developer->id,
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this
            ->actingAs($developer)
            ->patchJson($this->decideRoute($developer, $timeLog, $owner->currentTeam), ['decision' => 'approved']);

        $response->assertForbidden();
    }

    public function test_a_pending_entry_cannot_be_decided(): void
    {
        $manager = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $manager->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager']);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $manager->id,
            'team_id' => $manager->currentTeam->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $response = $this
            ->actingAs($manager)
            ->patchJson($this->decideRoute($manager, $timeLog), ['decision' => 'approved']);

        $response->assertForbidden();
    }

    public function test_an_entry_from_another_team_cannot_be_reached_through_the_users_own_team_url(): void
    {
        $user = User::factory()->create();
        $foreignTimeLog = TimeLog::factory()->create(['approval_status' => ApprovalStatus::Submitted]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->decideRoute($user, $foreignTimeLog), ['decision' => 'approved']);

        $response->assertNotFound();
    }

    private function decideRoute(User $user, TimeLog $timeLog, ?Team $team = null): string
    {
        return route('timesheet-approvals.decide', [
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'time_log' => $timeLog,
        ]);
    }
}

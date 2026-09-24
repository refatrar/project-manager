<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\TeamModulePermission;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\TimeLog;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\TodoList;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Cross-module checks that a team role's stored permissions are what
 * actually decide access — removing a permission from a role takes the
 * ability away on the server, not just the button — plus the
 * no-self-approval and no-escalation rules.
 */
class TeamPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_member_without_projects_view_cannot_open_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $user = $this->memberWithPermissions($team, [TeamModulePermission::ViewMeetings]);
        $project = Project::factory()->create(['team_id' => $team->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => ProjectMemberRole::Manager->value]);

        $this->actingAs($user)
            ->get(route('projects.show', ['current_team' => $team->slug, 'project' => $project]))
            ->assertForbidden();
    }

    public function test_a_plain_project_member_sees_the_project_without_its_budget(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        app(TeamAccessControl::class)->ensureCatalogue();
        $project = Project::factory()->create(['team_id' => $team->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectMemberRole::Developer->value]);

        $this->actingAs($member)
            ->get(route('projects.show', ['current_team' => $team->slug, 'project' => $project]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageProject', false)
                ->has('project.description')
                ->missing('project.budget')
                ->missing('project.estimated_hours'));
    }

    public function test_a_team_lead_cannot_approve_their_own_time_off(): void
    {
        $lead = User::factory()->create();
        $team = $lead->currentTeam;
        $timeOffRequest = TimeOffRequest::factory()->create(['user_id' => $lead->id, 'team_id' => $team->id]);

        $this->actingAs($lead)
            ->patchJson(route('time-off-requests.decide', ['current_team' => $team->slug, 'time_off_request' => $timeOffRequest]), [
                'decision' => 'approved',
            ])
            ->assertForbidden();

        $this->actingAs($lead)
            ->get(route('time-off-requests.index', ['current_team' => $team->slug]))
            ->assertInertia(fn (Assert $page) => $page->has('pendingApprovals', 0));
    }

    public function test_a_project_manager_cannot_approve_their_own_time_log(): void
    {
        $manager = User::factory()->create();
        $team = $manager->currentTeam;
        $project = Project::factory()->create(['team_id' => $team->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => ProjectMemberRole::Manager->value]);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $manager->id,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('timesheet-approvals.decide', ['current_team' => $team->slug, 'time_log' => $timeLog]), [
                'decision' => 'approved',
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('timesheet-approvals.index', ['current_team' => $team->slug]))
            ->assertInertia(fn (Assert $page) => $page->has('entries', 0));
    }

    public function test_a_team_wide_timesheet_approver_can_decide_entries_on_any_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $approver = $this->memberWithPermissions($team, [TeamModulePermission::DecideTimesheets]);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $timeLog = TimeLog::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $this->actingAs($approver)
            ->get(route('timesheet-approvals.index', ['current_team' => $team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 1)
                ->where('canApproveTimesheets', true));

        $this->actingAs($approver)
            ->patchJson(route('timesheet-approvals.decide', ['current_team' => $team->slug, 'time_log' => $timeLog]), [
                'decision' => 'approved',
            ])
            ->assertOk();
    }

    public function test_a_role_without_todos_manage_cannot_create_or_edit_personal_lists(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $user = $this->memberWithPermissions($team, [TeamModulePermission::ViewMyDay]);
        $list = TodoList::factory()->create(['owner_id' => $user->id, 'team_id' => $team->id]);

        $this->actingAs($user)
            ->postJson(route('todo-lists.store', ['current_team' => $team->slug]), [
                'name' => 'Groceries',
                'type' => 'custom',
                'status' => 'open',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson(route('todo-lists.update', ['current_team' => $team->slug, 'todo_list' => $list]), [
                'name' => 'Renamed',
                'status' => 'open',
            ])
            ->assertForbidden();
    }

    public function test_the_timesheet_cannot_be_submitted_without_the_submit_permission(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $user = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewTimesheet,
            TeamModulePermission::ManageTimeLogs,
        ]);
        TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'logged_on' => Carbon::today()->toDateString(),
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get(route('timesheet.index', ['current_team' => $team->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('canSubmit', false));
    }

    public function test_member_update_cannot_change_your_own_role_the_lead_or_a_stronger_member(): void
    {
        $lead = User::factory()->create();
        $team = $lead->currentTeam;
        $actor = $this->memberWithPermissions($team, [TeamModulePermission::ViewDashboard, TeamModulePermission::UpdateMember]);
        $weakerSlug = $this->roleWithPermissions([TeamModulePermission::ViewDashboard]);
        $weaker = User::factory()->create();
        $team->members()->attach($weaker, ['role' => $weakerSlug]);
        $stronger = User::factory()->create();
        $team->members()->attach($stronger, ['role' => TeamRole::Member->value]);

        // Allowed: a role the actor fully holds, on someone below them.
        $this->actingAs($actor)
            ->patch(route('teams.members.update', [$team, $weaker]), ['role' => $weakerSlug])
            ->assertRedirect();

        // Not their own role.
        $this->actingAs($actor)
            ->patch(route('teams.members.update', [$team, $actor]), ['role' => $weakerSlug])
            ->assertForbidden();

        // Not the team lead.
        $this->actingAs($actor)
            ->patch(route('teams.members.update', [$team, $lead]), ['role' => $weakerSlug])
            ->assertForbidden();

        // Not someone whose role holds permissions the actor lacks.
        $this->actingAs($actor)
            ->patch(route('teams.members.update', [$team, $stronger]), ['role' => $weakerSlug])
            ->assertForbidden();

        // And never to a role stronger than their own.
        $this->actingAs($actor)
            ->patchJson(route('teams.members.update', [$team, $weaker]), ['role' => TeamRole::Member->value])
            ->assertJsonValidationErrors('role');

        $this->assertSame(TeamRole::Member->value, $stronger->membershipRoleSlug($team));
    }

    public function test_invitations_cannot_grant_a_role_stronger_than_the_inviters(): void
    {
        $lead = User::factory()->create();
        $team = $lead->currentTeam;
        $actor = $this->memberWithPermissions($team, [TeamModulePermission::ViewDashboard, TeamModulePermission::CreateInvitation]);

        $this->actingAs($actor)
            ->postJson(route('teams.invitations.store', $team), [
                'email' => 'second-account@example.com',
                'role' => TeamRole::Member->value,
            ])
            ->assertJsonValidationErrors('role');
    }

    public function test_a_missing_role_row_grants_nothing_once_roles_are_seeded(): void
    {
        $lead = User::factory()->create();
        $team = $lead->currentTeam;
        app(TeamAccessControl::class)->ensureCatalogue();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        Role::query()->where('slug', TeamRole::Member->value)->whereNull('team_id')->delete();

        $this->assertSame([], app(TeamAccessControl::class)->grantedNames($member, $team));
    }

    public function test_start_lands_a_role_without_the_dashboard_on_its_first_allowed_page(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $user = $this->memberWithPermissions($team, [TeamModulePermission::ViewMyDay]);
        $user->switchTeam($team);

        $this->actingAs($user)
            ->get(route('start'))
            ->assertRedirect(route('my-day', ['current_team' => $team->slug]));
    }

    /**
     * @param  list<TeamModulePermission>  $permissions
     */
    private function memberWithPermissions(Team $team, array $permissions): User
    {
        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => $this->roleWithPermissions($permissions)]);

        return $user;
    }

    /**
     * @param  list<TeamModulePermission>  $permissions
     */
    private function roleWithPermissions(array $permissions): string
    {
        app(TeamAccessControl::class)->ensureCatalogue();

        $slug = 'custom-'.Str::lower(Str::random(8));
        $role = Role::query()->create([
            'team_id' => null,
            'guard_name' => 'web',
            'slug' => $slug,
            'name' => 'Custom '.$slug,
            'is_system' => false,
        ]);
        $role->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', array_map(fn (TeamModulePermission $permission): string => $permission->value, $permissions))
                ->pluck('id')
                ->all(),
        );

        return $slug;
    }
}

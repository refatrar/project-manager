<?php

namespace Tests\Feature\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\TeamModulePermission;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Permission-level rules of `MeetingPolicy`: `meetings.view` is the baseline
 * for every ability, `meetings.view-all` only widens visibility (it never
 * grants management), and `meetings.manage-all` grants management.
 */
class MeetingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_all_alone_does_not_grant_managing_a_meeting(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $viewer = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::ViewAllMeetings,
        ]);
        $project = Project::factory()->for($team)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->get($this->route('meetings.show', $team, $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.cancel', false)
                ->where('can.delete', false)
                ->where('can.recordMinutes', false),
            );

        $this->actingAs($viewer)
            ->putJson($this->route('meetings.update', $team, $meeting), $this->payload($meeting, ['title' => 'Hijacked']))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->patchJson($this->route('meetings.cancel', $team, $meeting))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->deleteJson($this->route('meetings.destroy', $team, $meeting))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->putJson($this->route('meetings.minutes.update', $team, $meeting), ['minutes' => 'Nope.'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('meetings.agenda-items.store', ['current_team' => $team->slug, 'meeting' => $meeting]), [
                'title' => 'Sneaky item',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id, 'title' => 'Hijacked']);
    }

    public function test_manage_all_grants_managing_a_meeting_the_user_did_not_organize(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::ManageAllMeetings,
        ]);
        $meeting = Meeting::factory()->create(['team_id' => $team->id, 'organized_by' => $owner->id]);

        $this->actingAs($manager)
            ->putJson($this->route('meetings.update', $team, $meeting), $this->payload($meeting, ['title' => 'Managed']))
            ->assertOk();

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'title' => 'Managed']);
    }

    public function test_manage_all_can_open_a_project_meeting_it_can_manage(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::ManageAllMeetings,
        ]);
        $project = Project::factory()->for($team)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $this->actingAs($manager)
            ->get($this->route('meetings.show', $team, $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.update', true));
    }

    public function test_without_meetings_view_a_project_member_cannot_open_the_project_meeting(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = $this->memberWithPermissions($team, [TeamModulePermission::ViewProjects]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $member->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $this->actingAs($member)
            ->get($this->route('meetings.show', $team, $meeting))
            ->assertForbidden();
    }

    public function test_without_meetings_view_a_project_manager_cannot_manage_the_project_meeting(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = $this->memberWithPermissions($team, [TeamModulePermission::ViewProjects]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $manager->id, 'role' => ProjectMemberRole::Manager]);
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $this->actingAs($manager)
            ->putJson($this->route('meetings.update', $team, $meeting), $this->payload($meeting, ['title' => 'Nope']))
            ->assertForbidden();
    }

    public function test_without_meetings_view_the_organizer_cannot_manage_their_own_meeting(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $organizer = $this->memberWithPermissions($team, [TeamModulePermission::CreateMeetings]);
        $meeting = Meeting::factory()->create(['team_id' => $team->id, 'organized_by' => $organizer->id]);

        $this->actingAs($organizer)
            ->get($this->route('meetings.show', $team, $meeting))
            ->assertForbidden();

        $this->actingAs($organizer)
            ->deleteJson($this->route('meetings.destroy', $team, $meeting))
            ->assertForbidden();
    }

    public function test_scheduling_requires_meetings_view_as_well_as_meetings_create(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $scheduler = $this->memberWithPermissions($team, [TeamModulePermission::CreateMeetings]);

        $this->actingAs($scheduler)
            ->postJson($this->route('meetings.store', $team), [
                'title' => 'Standup',
                'type' => 'general',
                'scheduled_start' => now()->addDay()->toDateTimeString(),
                'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('meetings', ['title' => 'Standup']);
    }

    public function test_a_meeting_cannot_be_scheduled_on_a_project_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $scheduler = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewProjects,
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::CreateMeetings,
        ]);
        $hiddenProject = Project::factory()->for($team)->create();

        $this->actingAs($scheduler)
            ->postJson($this->route('meetings.store', $team), [
                'project_id' => $hiddenProject->id,
                'title' => 'Hidden project sync',
                'type' => 'general',
                'scheduled_start' => now()->addDay()->toDateTimeString(),
                'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('meetings', ['title' => 'Hidden project sync']);
    }

    public function test_a_meeting_can_be_scheduled_on_a_project_the_user_belongs_to(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $scheduler = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewProjects,
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::CreateMeetings,
        ]);
        $project = Project::factory()->for($team)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $scheduler->id]);

        $this->actingAs($scheduler)
            ->postJson($this->route('meetings.store', $team), [
                'project_id' => $project->id,
                'title' => 'Project sync',
                'type' => 'general',
                'scheduled_start' => now()->addDay()->toDateTimeString(),
                'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('meetings', ['title' => 'Project sync', 'project_id' => $project->id]);
    }

    public function test_the_organizer_cannot_move_a_meeting_onto_a_project_they_cannot_see(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $organizer = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewProjects,
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::CreateMeetings,
        ]);
        $hiddenProject = Project::factory()->for($team)->create();
        $meeting = Meeting::factory()->create(['team_id' => $team->id, 'organized_by' => $organizer->id]);

        $this->actingAs($organizer)
            ->putJson($this->route('meetings.update', $team, $meeting), $this->payload($meeting, [
                'project_id' => $hiddenProject->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'project_id' => null]);
    }

    public function test_manage_all_can_edit_a_project_meeting_in_place_without_seeing_the_project(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = $this->memberWithPermissions($team, [
            TeamModulePermission::ViewMeetings,
            TeamModulePermission::ManageAllMeetings,
        ]);
        $project = Project::factory()->for($team)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $this->actingAs($manager)
            ->putJson($this->route('meetings.update', $team, $meeting), $this->payload($meeting, [
                'project_id' => $project->id,
                'title' => 'Renamed in place',
            ]))
            ->assertOk();
    }

    public function test_the_workspace_reports_the_organizers_abilities(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $this->actingAs($user)
            ->get($this->route('meetings.show', $user->currentTeam, $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', true)
                ->where('can.cancel', true)
                ->where('can.delete', true)
                ->where('can.recordMinutes', true)
                ->where('can.startTimer', true),
            );
    }

    public function test_the_workspace_hides_management_from_a_plain_participant(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $participant = $this->memberWithPermissions($team, [TeamModulePermission::ViewMeetings]);
        $meeting = Meeting::factory()->create(['team_id' => $team->id, 'organized_by' => $owner->id]);

        $this->actingAs($participant)
            ->get($this->route('meetings.show', $team, $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.cancel', false)
                ->where('can.delete', false)
                ->where('can.recordMinutes', false)
                ->where('can.startTimer', false),
            );
    }

    /**
     * A team member on a fresh custom role holding exactly the given
     * permissions.
     *
     * @param  list<TeamModulePermission>  $permissions
     */
    private function memberWithPermissions(Team $team, array $permissions): User
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

        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => $slug]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Meeting $meeting, array $overrides = []): array
    {
        return array_merge([
            'title' => $meeting->title,
            'type' => $meeting->type->value,
            'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
            'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
        ], $overrides);
    }

    private function route(string $name, Team $team, ?Meeting $meeting = null): string
    {
        return route($name, array_filter([
            'current_team' => $team->slug,
            'meeting' => $meeting,
        ]));
    }
}

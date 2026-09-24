<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamModulePermission;
use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTimerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attendee_can_start_and_stop_the_timer(): void
    {
        $organizer = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $organizer->currentTeam->id, 'organized_by' => $organizer->id]);

        $startResponse = $this
            ->actingAs($organizer)
            ->postJson($this->timerRoute($organizer, 'meetings.timer.start', $meeting));

        $startResponse->assertOk();
        $startResponse->assertJsonPath('timer.running.id', fn ($id) => $id !== null);
        $this->assertDatabaseHas('time_logs', [
            'meeting_id' => $meeting->id,
            'user_id' => $organizer->id,
            'ended_at' => null,
        ]);

        $stopResponse = $this
            ->actingAs($organizer)
            ->patchJson($this->timerRoute($organizer, 'meetings.timer.stop', $meeting));

        $stopResponse->assertOk();
        $stopResponse->assertJsonPath('timer.running', null);
        $this->assertDatabaseMissing('time_logs', [
            'meeting_id' => $meeting->id,
            'user_id' => $organizer->id,
            'ended_at' => null,
        ]);
    }

    public function test_starting_a_second_timer_while_one_is_running_is_rejected(): void
    {
        $user = User::factory()->create();
        $meetingA = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $meetingB = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $this->actingAs($user)->postJson($this->timerRoute($user, 'meetings.timer.start', $meetingA))->assertOk();

        $response = $this->actingAs($user)->postJson($this->timerRoute($user, 'meetings.timer.start', $meetingB));

        $response->assertStatus(422);
    }

    public function test_a_user_with_no_visibility_into_a_project_meeting_cannot_start_its_timer(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->currentTeam->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($outsider)
            ->postJson($this->timerRoute($outsider, 'meetings.timer.start', $meeting, $owner->currentTeam));

        $response->assertForbidden();
    }

    public function test_a_user_who_cannot_log_time_cannot_start_a_meeting_timer(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        // A role that can see meetings but lacks `time-logs.manage`.
        app(TeamAccessControl::class)->ensureCatalogue();
        $role = Role::query()->create([
            'team_id' => null,
            'guard_name' => 'web',
            'slug' => 'meeting-viewer',
            'name' => 'Meeting viewer',
            'is_system' => false,
        ]);
        $role->syncPermissions([
            Permission::query()->where('guard_name', 'web')
                ->where('name', TeamModulePermission::ViewMeetings->value)
                ->firstOrFail()->id,
        ]);
        $viewer = User::factory()->create();
        $team->members()->attach($viewer, ['role' => 'meeting-viewer']);

        $meeting = Meeting::factory()->create(['team_id' => $team->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($viewer)
            ->postJson($this->timerRoute($viewer, 'meetings.timer.start', $meeting, $team));

        $response->assertForbidden();
        $this->assertDatabaseMissing('time_logs', ['meeting_id' => $meeting->id, 'user_id' => $viewer->id]);
    }

    private function timerRoute(User $user, string $name, Meeting $meeting, ?Team $team = null): string
    {
        return route($name, [
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'meeting' => $meeting,
        ]);
    }
}

<?php

namespace Tests\Feature\OMS;

use App\Enums\MeetingStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_wide_meeting_is_visible_to_any_team_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id]);

        $response = $this
            ->actingAs($member)
            ->get($this->meetingsRoute($member, 'meetings.index', null, $owner->currentTeam));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('meetings.data', 1)
            ->where('meetings.data.0.id', $meeting->id),
        );
    }

    public function test_a_project_meeting_is_hidden_from_a_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->currentTeam->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'project_id' => $project->id]);

        $response = $this
            ->actingAs($outsider)
            ->get($this->meetingsRoute($outsider, 'meetings.index', null, $owner->currentTeam));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->has('meetings.data', 0));
    }

    public function test_a_project_meeting_is_visible_to_a_project_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $member->id]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'project_id' => $project->id]);

        $response = $this
            ->actingAs($member)
            ->get($this->meetingsRoute($member, 'meetings.index', null, $owner->currentTeam));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('meetings.data', 1)
            ->where('meetings.data.0.id', $meeting->id),
        );
    }

    public function test_scheduling_a_meeting_adds_the_creator_as_an_accepted_organizer(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->meetingsRoute($user, 'meetings.store'), [
                'title' => 'Sprint planning',
                'type' => 'planning',
                'scheduled_start' => now()->addDay()->toDateTimeString(),
                'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('meeting.title', 'Sprint planning');

        $this->assertDatabaseHas('meetings', [
            'team_id' => $user->currentTeam->id,
            'title' => 'Sprint planning',
            'organized_by' => $user->id,
        ]);
        $this->assertDatabaseHas('meeting_attendees', [
            'user_id' => $user->id,
            'role' => 'organizer',
            'attendance_status' => 'accepted',
        ]);
    }

    public function test_scheduled_end_must_be_after_scheduled_start(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->meetingsRoute($user, 'meetings.store'), [
                'title' => 'Bad meeting',
                'type' => 'general',
                'scheduled_start' => now()->addDay()->toDateTimeString(),
                'scheduled_end' => now()->toDateTimeString(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_end']);
    }

    public function test_the_meeting_workspace_shows_its_attendees(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $meeting->attendees()->create(['user_id' => $user->id, 'role' => 'organizer', 'attendance_status' => 'accepted']);

        $response = $this
            ->actingAs($user)
            ->get($this->meetingsRoute($user, 'meetings.show', $meeting));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('meetings/show')
            ->where('meeting.id', $meeting->id)
            ->has('attendees', 1),
        );
    }

    public function test_the_meeting_workspace_lists_the_projects_tasks_for_agenda_linking(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create(['code' => 'ACME']);
        $task = Task::factory()->for($project)->create(['title' => 'Fix login bug']);
        $meeting = Meeting::factory()->create([
            'team_id' => $user->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->meetingsRoute($user, 'meetings.show', $meeting));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('projectTasks', 1)
            ->where('projectTasks.0.id', $task->id)
            ->where('projectTasks.0.reference', "ACME-{$task->number}"),
        );
    }

    public function test_the_organizer_can_update_the_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->meetingsRoute($user, 'meetings.update', $meeting), [
                'title' => 'Renamed meeting',
                'type' => $meeting->type->value,
                'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
                'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'title' => 'Renamed meeting']);
    }

    public function test_a_non_organizer_participant_cannot_update_the_meeting(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $owner->currentTeam->members()->attach($participant, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($participant)
            ->putJson($this->meetingsRoute($participant, 'meetings.update', $meeting, $owner->currentTeam), [
                'title' => 'Hijacked',
                'type' => $meeting->type->value,
                'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
                'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
            ]);

        $response->assertForbidden();
    }

    public function test_a_project_manager_can_update_a_project_meeting_they_did_not_organize(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $owner->currentTeam->members()->attach($manager, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();
        ProjectMember::factory()->for($project)->create(['user_id' => $manager->id, 'role' => ProjectMemberRole::Manager]);
        $meeting = Meeting::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($manager)
            ->putJson($this->meetingsRoute($manager, 'meetings.update', $meeting, $owner->currentTeam), [
                'title' => 'Managed update',
                'type' => $meeting->type->value,
                'scheduled_start' => $meeting->scheduled_start->toDateTimeString(),
                'scheduled_end' => $meeting->scheduled_end->toDateTimeString(),
            ]);

        $response->assertOk();
    }

    public function test_the_organizer_can_cancel_the_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->meetingsRoute($user, 'meetings.cancel', $meeting));

        $response->assertOk();
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'status' => MeetingStatus::Cancelled->value]);
    }

    public function test_the_organizer_can_delete_the_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->meetingsRoute($user, 'meetings.destroy', $meeting));

        $response->assertOk();
        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }

    public function test_a_meeting_cannot_be_reached_through_another_teams_url(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $otherTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->get($this->meetingsRoute($user, 'meetings.show', $meeting));

        $response->assertNotFound();
    }

    private function meetingsRoute(User $user, string $name, ?Meeting $meeting = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'meeting' => $meeting,
        ]));
    }
}

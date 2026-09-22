<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAttendee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingAttendeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_user_can_be_invited(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $owner->currentTeam->members()->attach($invitee, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->attendeeRoute($owner, 'meetings.attendees.store', $meeting), [
                'user_id' => $invitee->id,
                'role' => 'participant',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meeting_attendees', [
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'attendance_status' => 'invited',
        ]);
    }

    public function test_a_guest_can_be_invited_by_name_and_email(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->attendeeRoute($owner, 'meetings.attendees.store', $meeting), [
                'guest_name' => 'Alex Client',
                'guest_email' => 'alex@example.test',
                'role' => 'participant',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meeting_attendees', [
            'meeting_id' => $meeting->id,
            'guest_name' => 'Alex Client',
            'guest_email' => 'alex@example.test',
        ]);
    }

    public function test_inviting_someone_requires_either_a_user_or_a_guest(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->postJson($this->attendeeRoute($owner, 'meetings.attendees.store', $meeting), [
                'role' => 'participant',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id', 'guest_email']);
    }

    public function test_a_participant_cannot_invite_another_attendee(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $owner->currentTeam->members()->attach($participant, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($participant)
            ->postJson($this->attendeeRoute($participant, 'meetings.attendees.store', $meeting, $owner->currentTeam), [
                'guest_name' => 'Alex Client',
                'guest_email' => 'alex@example.test',
                'role' => 'participant',
            ]);

        $response->assertForbidden();
    }

    public function test_accepting_stamps_responded_at(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);
        $attendee = MeetingAttendee::factory()->for($meeting)->create(['user_id' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->putJson($this->attendeeRoute($owner, 'meetings.attendees.update', $meeting, null, $attendee), [
                'role' => 'participant',
                'attendance_status' => 'accepted',
            ]);

        $response->assertOk();
        $attendee->refresh();
        $this->assertSame('accepted', $attendee->attendance_status->value);
        $this->assertNotNull($attendee->responded_at);
    }

    public function test_marking_attended_stamps_joined_at(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);
        $attendee = MeetingAttendee::factory()->accepted()->for($meeting)->create(['user_id' => $owner->id]);

        $response = $this
            ->actingAs($owner)
            ->putJson($this->attendeeRoute($owner, 'meetings.attendees.update', $meeting, null, $attendee), [
                'role' => 'participant',
                'attendance_status' => 'attended',
            ]);

        $response->assertOk();
        $attendee->refresh();
        $this->assertNotNull($attendee->joined_at);
    }

    public function test_an_attendee_can_be_removed(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);
        $attendee = MeetingAttendee::factory()->for($meeting)->create();

        $response = $this
            ->actingAs($owner)
            ->deleteJson($this->attendeeRoute($owner, 'meetings.attendees.destroy', $meeting, null, $attendee));

        $response->assertOk();
        $this->assertDatabaseMissing('meeting_attendees', ['id' => $attendee->id]);
    }

    public function test_a_meeting_from_another_team_cannot_be_reached_through_the_users_own_team_url(): void
    {
        $user = User::factory()->create();
        $foreignMeeting = Meeting::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->attendeeRoute($user, 'meetings.attendees.store', $foreignMeeting), [
                'user_id' => $user->id,
                'role' => 'participant',
            ]);

        $response->assertNotFound();
    }

    private function attendeeRoute(User $user, string $name, Meeting $meeting, ?Team $team = null, ?MeetingAttendee $attendee = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'meeting' => $meeting,
            'attendee' => $attendee,
        ]));
    }
}

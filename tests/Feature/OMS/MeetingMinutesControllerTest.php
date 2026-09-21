<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Meetings\MinutesPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingMinutesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizer_can_save_minutes(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->minutesRoute($user, 'meetings.minutes.update', $meeting), [
                'minutes' => 'We discussed the roadmap.',
                'decisions' => 'Ship next week.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'minutes' => 'We discussed the roadmap.',
            'decisions' => 'Ship next week.',
            'recorded_by' => $user->id,
        ]);
    }

    public function test_a_note_taker_attendee_can_save_minutes_without_managing_the_meeting(): void
    {
        $owner = User::factory()->create();
        $noteTaker = User::factory()->create();
        $owner->currentTeam->members()->attach($noteTaker, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);
        $meeting->attendees()->create(['user_id' => $noteTaker->id, 'role' => 'note_taker', 'attendance_status' => 'accepted']);

        $response = $this
            ->actingAs($noteTaker)
            ->putJson($this->minutesRoute($noteTaker, 'meetings.minutes.update', $meeting, $owner->currentTeam), [
                'minutes' => 'Notes from the note taker.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'recorded_by' => $noteTaker->id]);
    }

    public function test_a_plain_participant_cannot_save_minutes(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $owner->currentTeam->members()->attach($participant, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);
        $meeting->attendees()->create(['user_id' => $participant->id, 'role' => 'participant', 'attendance_status' => 'accepted']);

        $response = $this
            ->actingAs($participant)
            ->putJson($this->minutesRoute($participant, 'meetings.minutes.update', $meeting, $owner->currentTeam), [
                'minutes' => 'Sneaky notes.',
            ]);

        $response->assertForbidden();
    }

    public function test_publishing_stamps_the_timestamp_and_recorder(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting));

        $response->assertOk();
        $meeting->refresh();
        $this->assertNotNull($meeting->minutes_published_at);
        $this->assertSame($user->id, $meeting->recorded_by);
    }

    public function test_publishing_twice_does_not_move_the_timestamp(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);

        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();
        $firstPublishedAt = $meeting->fresh()->minutes_published_at;

        Carbon::setTestNow(Carbon::parse('2026-01-02 10:00:00'));
        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();
        Carbon::setTestNow(null);

        $this->assertTrue($firstPublishedAt->equalTo($meeting->fresh()->minutes_published_at));
    }

    public function test_minutes_can_still_be_edited_after_publishing(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();

        $response = $this
            ->actingAs($user)
            ->putJson($this->minutesRoute($user, 'meetings.minutes.update', $meeting), [
                'minutes' => 'Fixed a typo.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'minutes' => 'Fixed a typo.']);
    }

    public function test_publishing_notifies_registered_and_guest_attendees(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $registeredAttendee = User::factory()->create();
        $user->currentTeam->members()->attach($registeredAttendee, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $meeting->attendees()->create(['user_id' => $registeredAttendee->id, 'role' => 'participant', 'attendance_status' => 'accepted']);
        $meeting->attendees()->create(['guest_name' => 'Guest', 'guest_email' => 'guest@example.com', 'role' => 'participant']);

        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();

        Notification::assertSentTo($registeredAttendee, MinutesPublished::class);
        Notification::assertSentOnDemand(
            MinutesPublished::class,
            fn (MinutesPublished $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'guest@example.com',
        );
    }

    public function test_publishing_twice_does_not_send_a_second_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $meeting->attendees()->create(['user_id' => $user->id, 'role' => 'organizer', 'attendance_status' => 'accepted']);

        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();
        $this->actingAs($user)->patchJson($this->minutesRoute($user, 'meetings.minutes.publish', $meeting))->assertOk();

        Notification::assertSentToTimes($user, MinutesPublished::class, 1);
    }

    public function test_a_plain_participant_cannot_publish_minutes(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $owner->currentTeam->members()->attach($participant, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($participant)
            ->patchJson($this->minutesRoute($participant, 'meetings.minutes.publish', $meeting, $owner->currentTeam));

        $response->assertForbidden();
    }

    private function minutesRoute(User $user, string $name, Meeting $meeting, ?Team $team = null): string
    {
        return route($name, [
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'meeting' => $meeting,
        ]);
    }
}

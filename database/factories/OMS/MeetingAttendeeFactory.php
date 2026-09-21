<?php

namespace Database\Factories\OMS;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingAttendeeRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAttendee>
 */
class MeetingAttendeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'user_id' => User::factory(),
            'role' => MeetingAttendeeRole::Participant,
            'attendance_status' => MeetingAttendanceStatus::Invited,
        ];
    }

    /**
     * Indicate that the attendee is an external guest, not a registered user.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the attendee has accepted the invitation.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attendance_status' => MeetingAttendanceStatus::Accepted,
            'responded_at' => now(),
        ]);
    }
}

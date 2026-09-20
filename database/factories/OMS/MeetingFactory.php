<?php

namespace Database\Factories\OMS;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\OMS\Meeting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledStart = now()->addDays(fake()->numberBetween(-14, 14));

        return [
            'team_id' => Team::factory(),
            'title' => fake()->sentence(4),
            'type' => MeetingType::General,
            'status' => MeetingStatus::Scheduled,
            'agenda' => fake()->optional()->paragraph(),
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledStart->copy()->addMinutes(fake()->randomElement([15, 30, 60])),
        ];
    }

    /**
     * Indicate that the meeting happened and its minutes were captured.
     */
    public function withMinutes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MeetingStatus::Completed,
            'minutes' => fake()->paragraphs(2, true),
            'decisions' => fake()->sentence(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'minutes_published_at' => now(),
        ]);
    }
}

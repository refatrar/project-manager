<?php

namespace Database\Factories\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeLog>
 */
class TimeLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subDays(fake()->numberBetween(0, 14))->setTime(fake()->numberBetween(9, 16), 0);
        $durationMinutes = fake()->randomElement([30, 60, 90, 120, 180, 240]);

        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'description' => fake()->optional()->sentence(),
            'activity_type' => TimeLogActivityType::Development,
            'source' => TimeLogSource::Manual,
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes($durationMinutes),
            'duration_minutes' => $durationMinutes,
            'logged_on' => $startedAt->toDateString(),
            'approval_status' => ApprovalStatus::Pending,
        ];
    }

    /**
     * Indicate that the timer is still running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => TimeLogSource::Timer,
            'started_at' => now()->subMinutes(30),
            'ended_at' => null,
            'duration_minutes' => 0,
            'logged_on' => today(),
        ]);
    }

    /**
     * Indicate that the entry has been approved for billing.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}

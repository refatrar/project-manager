<?php

namespace Database\Factories\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeOffType;
use App\Models\OMS\TimeOffRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOffRequest>
 */
class TimeOffRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->addDays(fake()->numberBetween(1, 30));

        return [
            'user_id' => User::factory(),
            'type' => TimeOffType::Vacation,
            'status' => ApprovalStatus::Pending,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(fake()->numberBetween(0, 4)),
            'is_full_day' => true,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the request has been approved and reduces capacity.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}

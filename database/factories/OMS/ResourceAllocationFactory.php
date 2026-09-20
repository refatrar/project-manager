<?php

namespace Database\Factories\OMS;

use App\Enums\AllocationStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceAllocation>
 */
class ResourceAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->startOfWeek();

        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'status' => AllocationStatus::Planned,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(4),
            'hours_per_day' => fake()->randomElement([2, 4, 6, 8]),
            'allocation_percentage' => fake()->randomElement([25, 50, 75, 100]),
        ];
    }

    /**
     * Indicate that the booking is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AllocationStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the booking no longer reserves capacity.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AllocationStatus::Cancelled,
        ]);
    }
}

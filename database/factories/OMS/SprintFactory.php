<?php

namespace Database\Factories\OMS;

use App\Enums\SprintStatus;
use App\Models\OMS\Project;
use App\Models\OMS\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
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
            'project_id' => Project::factory(),
            'name' => 'Sprint '.fake()->unique()->numberBetween(1, 500),
            'goal' => fake()->optional()->sentence(),
            'status' => SprintStatus::Planned,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(13),
            'capacity_hours' => fake()->numberBetween(40, 400),
        ];
    }

    /**
     * Indicate that the sprint is currently running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SprintStatus::Active,
            'started_at' => now(),
        ]);
    }
}

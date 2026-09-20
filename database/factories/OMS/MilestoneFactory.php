<?php

namespace Database\Factories\OMS;

use App\Enums\MilestoneStatus;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => MilestoneStatus::Pending,
            'due_on' => now()->addDays(fake()->numberBetween(7, 120)),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the milestone has been reached.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MilestoneStatus::Completed,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }
}

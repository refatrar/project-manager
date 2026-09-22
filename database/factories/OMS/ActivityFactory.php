<?php

namespace Database\Factories\OMS;

use App\Models\OMS\Activity;
use App\Models\OMS\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'event' => 'project.created',
            'description' => fake()->sentence(),
            'properties' => null,
        ];
    }

    /**
     * Indicate that the activity is not tied to any project.
     */
    public function teamOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => null,
        ]);
    }
}

<?php

namespace Database\Factories\OMS;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Models\OMS\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word().' Platform');
        $startDate = fake()->dateTimeBetween('-3 months', '+1 month');

        return [
            'team_id' => Team::factory(),
            'code' => Str::upper(Str::random(6)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'description' => fake()->optional()->paragraph(),
            'status' => ProjectStatus::Active,
            'priority' => Priority::Medium,
            'health' => ProjectHealth::OnTrack,
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+6 months'),
            'estimated_hours' => fake()->numberBetween(80, 1200),
        ];
    }

    /**
     * Indicate that the project is still being planned.
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProjectStatus::Planning,
        ]);
    }

    /**
     * Indicate that the project is finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProjectStatus::Completed,
            'progress_percentage' => 100,
            'actual_end_date' => now(),
        ]);
    }
}

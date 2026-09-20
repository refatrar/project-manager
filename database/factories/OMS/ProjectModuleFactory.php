<?php

namespace Database\Factories\OMS;

use App\Enums\Priority;
use App\Enums\ProjectModuleStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectModule>
 */
class ProjectModuleFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => ProjectModuleStatus::Planning,
            'priority' => Priority::Medium,
            'position' => fake()->numberBetween(0, 20),
            'estimated_hours' => fake()->numberBetween(8, 160),
        ];
    }

    /**
     * Indicate that the module is a child of the given module.
     */
    public function childOf(ProjectModule $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $parent->project_id,
            'parent_id' => $parent->id,
        ]);
    }
}

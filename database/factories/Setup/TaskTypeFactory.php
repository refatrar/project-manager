<?php

namespace Database\Factories\Setup;

use App\Enums\TaskTypeStatus;
use App\Models\Setup\TaskType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskType>
 */
class TaskTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => TaskTypeStatus::Active,
        ];
    }

    /**
     * Indicate that the task type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskTypeStatus::Inactive,
        ]);
    }
}

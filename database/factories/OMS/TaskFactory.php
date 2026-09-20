<?php

namespace Database\Factories\OMS;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\Setup\TaskType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'task_type_id' => TaskType::factory(),
            'number' => fake()->unique()->numberBetween(1, 100000),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Backlog,
            'priority' => Priority::Medium,
            'estimated_hours' => fake()->randomFloat(2, 0.5, 40),
            'position' => fake()->numberBetween(0, 100),
            'due_at' => now()->addDays(fake()->numberBetween(1, 45)),
        ];
    }

    /**
     * Indicate that the task is being worked on.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is finished.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::Done,
            'progress_percentage' => 100,
            'completed_at' => now(),
            'closed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is open and past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::InProgress,
            'due_at' => now()->subDays(fake()->numberBetween(1, 20)),
        ]);
    }
}

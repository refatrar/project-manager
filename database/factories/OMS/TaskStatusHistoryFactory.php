<?php

namespace Database\Factories\OMS;

use App\Enums\TaskStatus;
use App\Models\OMS\Task;
use App\Models\OMS\TaskStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatusHistory>
 */
class TaskStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'from_status' => TaskStatus::Backlog,
            'to_status' => TaskStatus::InProgress,
            'note' => null,
            'duration_minutes' => fake()->numberBetween(30, 2880),
            'changed_at' => now(),
        ];
    }

    /**
     * Indicate that this is the task's first transition, with no prior status.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_status' => null,
            'to_status' => TaskStatus::Backlog,
            'duration_minutes' => null,
        ]);
    }
}

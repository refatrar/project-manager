<?php

namespace Database\Factories\OMS;

use App\Enums\TaskDependencyType;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDependency>
 */
class TaskDependencyFactory extends Factory
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
            'related_task_id' => Task::factory(),
            'type' => TaskDependencyType::BlockedBy,
        ];
    }

    /**
     * Indicate that the dependency is a "relates to" link.
     */
    public function relatesTo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TaskDependencyType::RelatesTo,
        ]);
    }
}

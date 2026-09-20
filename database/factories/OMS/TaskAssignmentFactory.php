<?php

namespace Database\Factories\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => TaskAssignmentRole::Assignee,
            'status' => TaskAssignmentStatus::Assigned,
            'allocated_hours' => fake()->randomFloat(2, 1, 24),
            'assigned_at' => now(),
        ];
    }

    /**
     * Indicate that the assignment is for review rather than delivery.
     */
    public function reviewer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TaskAssignmentRole::Reviewer,
        ]);
    }

    /**
     * Indicate that the assignment has been handed to somebody else.
     */
    public function reassigned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskAssignmentStatus::Reassigned,
            'unassigned_at' => now(),
        ]);
    }
}

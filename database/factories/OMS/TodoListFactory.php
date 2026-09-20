<?php

namespace Database\Factories\OMS;

use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Task;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TodoList>
 */
class TodoListFactory extends Factory
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
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true),
            'type' => TodoListType::Custom,
            'status' => TodoListStatus::Open,
        ];
    }

    /**
     * Indicate that the list is a user's plan for a single day.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TodoListType::Daily,
            'scheduled_for' => today(),
            'name' => 'Plan for '.today()->toFormattedDateString(),
        ]);
    }

    /**
     * Indicate that the list is a checklist on the given task.
     */
    public function checklistFor(Task $task): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TodoListType::TaskChecklist,
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'owner_id' => null,
        ]);
    }
}

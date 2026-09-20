<?php

namespace Database\Factories\OMS;

use App\Enums\Priority;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TodoItem>
 */
class TodoItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'todo_list_id' => TodoList::factory(),
            'title' => fake()->sentence(4),
            'notes' => fake()->optional()->sentence(),
            'priority' => Priority::Medium,
            'is_completed' => false,
            'estimated_minutes' => fake()->randomElement([15, 30, 45, 60, 120]),
            'position' => fake()->numberBetween(0, 30),
        ];
    }

    /**
     * Indicate that the item has been ticked off.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}

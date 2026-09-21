<?php

namespace Database\Factories\OMS;

use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->randomElement([5, 10, 15, 30]),
            'position' => fake()->numberBetween(0, 10),
            'is_discussed' => false,
        ];
    }

    /**
     * Indicate that the item has already been discussed.
     */
    public function discussed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_discussed' => true,
        ]);
    }
}

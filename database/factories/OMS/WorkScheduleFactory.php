<?php

namespace Database\Factories\OMS;

use App\Models\OMS\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(1, 5),
            'is_working_day' => true,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break_minutes' => 60,
            'capacity_hours' => 8,
            'effective_from' => now()->startOfYear(),
        ];
    }

    /**
     * Indicate that the day is not worked.
     */
    public function nonWorkingDay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_working_day' => false,
            'start_time' => null,
            'end_time' => null,
            'break_minutes' => 0,
            'capacity_hours' => 0,
        ]);
    }
}

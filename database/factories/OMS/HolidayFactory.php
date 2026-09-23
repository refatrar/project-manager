<?php

namespace Database\Factories\OMS;

use App\Models\OMS\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
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
            'date' => fake()->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
        ];
    }
}

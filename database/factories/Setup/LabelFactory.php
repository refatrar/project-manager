<?php

namespace Database\Factories\Setup;

use App\Models\Setup\Label;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}

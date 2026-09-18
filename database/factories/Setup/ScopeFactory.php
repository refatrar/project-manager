<?php

namespace Database\Factories\Setup;

use App\Enums\ScopeStatus;
use App\Models\Setup\Scope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scope>
 */
class ScopeFactory extends Factory
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
            'description' => fake()->optional()->sentence(),
            'status' => ScopeStatus::Active,
        ];
    }

    /**
     * Indicate that the scope is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScopeStatus::Inactive,
        ]);
    }
}

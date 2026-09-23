<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->word().'.'.fake()->word();

        return [
            'name' => $key,
            'guard_name' => 'admin',
            'label' => ucfirst(str_replace('.', ' ', $key)),
            'module' => 'general',
        ];
    }
}

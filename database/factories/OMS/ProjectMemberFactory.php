<?php

namespace Database\Factories\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => ProjectMemberRole::Developer,
            'status' => ProjectMemberStatus::Active,
            'allocation_percentage' => 100,
            'joined_on' => now()->subDays(fake()->numberBetween(0, 60)),
        ];
    }

    /**
     * Indicate that the member manages the project.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => ProjectMemberRole::Manager,
        ]);
    }

    /**
     * Indicate that the member has left the project.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProjectMemberStatus::Inactive,
            'left_on' => now(),
        ]);
    }
}

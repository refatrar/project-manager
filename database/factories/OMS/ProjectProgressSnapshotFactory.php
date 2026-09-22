<?php

namespace Database\Factories\OMS;

use App\Enums\ProjectHealth;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectProgressSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProgressSnapshot>
 */
class ProjectProgressSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalTasks = fake()->numberBetween(10, 60);
        $completedTasks = fake()->numberBetween(0, $totalTasks);

        return [
            'project_id' => Project::factory(),
            'snapshot_on' => now()->toDateString(),
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => fake()->numberBetween(0, $totalTasks - $completedTasks),
            'blocked_tasks' => 0,
            'overdue_tasks' => 0,
            'estimated_hours' => fake()->randomFloat(2, 40, 400),
            'logged_hours' => fake()->randomFloat(2, 0, 400),
            'remaining_hours' => fake()->randomFloat(2, 0, 200),
            'progress_percentage' => $totalTasks > 0 ? round($completedTasks / $totalTasks * 100) : 0,
            'commits_count' => 0,
            'merged_pull_requests_count' => 0,
            'health' => ProjectHealth::OnTrack,
        ];
    }

    /**
     * Indicate that the snapshot belongs to a specific sprint rather than the whole project.
     */
    public function forSprint(int $sprintId): static
    {
        return $this->state(fn (array $attributes): array => [
            'sprint_id' => $sprintId,
        ]);
    }
}

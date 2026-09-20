<?php

namespace Database\Factories\Git;

use App\Models\Git\GitCommit;
use App\Models\Git\GitRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitCommit>
 */
class GitCommitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $committedAt = now()->subDays(fake()->numberBetween(0, 30));

        return [
            'git_repository_id' => GitRepository::factory(),
            'sha' => fake()->unique()->sha1(),
            'message' => fake()->sentence(6),
            'branch' => 'main',
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'additions' => fake()->numberBetween(1, 400),
            'deletions' => fake()->numberBetween(0, 200),
            'changed_files' => fake()->numberBetween(1, 15),
            'authored_at' => $committedAt,
            'committed_at' => $committedAt,
        ];
    }
}

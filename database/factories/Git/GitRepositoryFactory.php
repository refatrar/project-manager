<?php

namespace Database\Factories\Git;

use App\Enums\GitProvider;
use App\Enums\GitSyncStatus;
use App\Models\Git\GitRepository;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitRepository>
 */
class GitRepositoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->unique()->userName().'/'.fake()->slug(2);

        return [
            'team_id' => Team::factory(),
            'provider' => GitProvider::GitHub,
            'full_name' => $fullName,
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'default_branch' => 'main',
            'visibility' => 'private',
            'web_url' => 'https://github.com/'.$fullName,
            'clone_url' => 'https://github.com/'.$fullName.'.git',
            'sync_status' => GitSyncStatus::Synced,
            'last_synced_at' => now(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the last synchronisation failed.
     */
    public function syncFailed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sync_status' => GitSyncStatus::Failed,
            'last_sync_error' => 'Authentication failed.',
        ]);
    }
}

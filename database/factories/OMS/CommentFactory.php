<?php

namespace Database\Factories\OMS;

use App\Models\OMS\Comment;
use App\Models\OMS\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
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
            'commentable_type' => Task::class,
            'commentable_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    /**
     * Indicate that the comment is a reply to another comment.
     */
    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $parent->team_id,
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Indicate that the comment is only visible to the team, not a client.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_internal' => true,
        ]);
    }
}

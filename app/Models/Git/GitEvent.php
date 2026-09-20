<?php

namespace App\Models\Git;

use App\Enums\GitEventStatus;
use App\Enums\GitEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Raw provider webhook payloads, kept so pushes can be replayed and audited.
 *
 * @property int $id
 * @property int $git_repository_id
 * @property GitEventType $event_type
 * @property string|null $external_event_id
 * @property string|null $ref
 * @property string|null $before_sha
 * @property string|null $after_sha
 * @property int $commits_count
 * @property string|null $actor_name
 * @property int|null $actor_id
 * @property array<string, mixed>|null $payload
 * @property GitEventStatus $status
 * @property string|null $error_message
 * @property Carbon|null $processed_at
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GitRepository $repository
 * @property-read User|null $actor
 */
#[Fillable([
    'event_type', 'external_event_id', 'ref', 'before_sha', 'after_sha', 'commits_count',
    'actor_name', 'actor_id', 'payload', 'occurred_at',
])]
class GitEvent extends Model
{
    /**
     * Get the repository the event was received for.
     *
     * @return BelongsTo<GitRepository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitRepository::class, 'git_repository_id');
    }

    /**
     * Get the resolved application user who triggered the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope the query to events that still need processing.
     *
     * @param  Builder<GitEvent>  $query
     * @return Builder<GitEvent>
     */
    #[Scope]
    protected function unprocessed(Builder $query): Builder
    {
        return $query->whereIn('status', [
            GitEventStatus::Pending->value,
            GitEventStatus::Failed->value,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => GitEventType::class,
            'status' => GitEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }
}

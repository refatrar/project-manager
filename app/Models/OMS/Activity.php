<?php

namespace App\Models\OMS;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit trail that also backs the project activity feed.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property int|null $user_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $event
 * @property string|null $description
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read User|null $user
 * @property-read Model|null $subject
 */
#[Fillable(['team_id', 'project_id', 'user_id', 'event', 'description', 'properties'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the team the activity happened in.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project the activity relates to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who triggered the activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the record the activity describes.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Get the payload used for the project activity feed. Assumes `user` is loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

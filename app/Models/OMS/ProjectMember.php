<?php

namespace App\Models\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Models\Concerns\HasAuditUsers;
use App\Models\User;
use Database\Factories\OMS\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ProjectMemberRole $role
 * @property ProjectMemberStatus $status
 * @property int $allocation_percentage
 * @property string|null $hourly_rate
 * @property Carbon|null $joined_on
 * @property Carbon|null $left_on
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'role', 'status', 'allocation_percentage', 'hourly_rate', 'joined_on', 'left_on',
])]
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the project the membership belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to memberships that are currently active.
     *
     * @param  Builder<ProjectMember>  $query
     * @return Builder<ProjectMember>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', ProjectMemberStatus::Active->value)->whereNull('left_on');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ProjectMemberRole::class,
            'status' => ProjectMemberStatus::class,
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }
}

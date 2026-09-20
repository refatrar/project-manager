<?php

namespace App\Models\OMS;

use App\Enums\AllocationStatus;
use App\Models\User;
use Database\Factories\OMS\ResourceAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A forward booking of a user's hours, which is what makes a day "occupied".
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property int|null $sprint_id
 * @property AllocationStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $hours_per_day
 * @property int|null $allocation_percentage
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Project $project
 * @property-read Task|null $task
 * @property-read Sprint|null $sprint
 */
#[Fillable([
    'user_id', 'project_id', 'task_id', 'sprint_id', 'status', 'starts_on', 'ends_on',
    'hours_per_day', 'allocation_percentage', 'notes',
])]
class ResourceAllocation extends Model
{
    /** @use HasFactory<ResourceAllocationFactory> */
    use HasFactory;

    /**
     * Get the booked user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project the booking is for.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task the booking is for.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the sprint the booking sits in.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Scope the query to bookings that reserve capacity in an inclusive date range.
     *
     * @param  Builder<ResourceAllocation>  $query
     * @return Builder<ResourceAllocation>
     */
    #[Scope]
    protected function reservingBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereIn('status', [
            AllocationStatus::Planned->value,
            AllocationStatus::Confirmed->value,
        ])
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AllocationStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}

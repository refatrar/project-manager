<?php

namespace App\Models\OMS;

use App\Enums\SprintStatus;
use App\Models\Concerns\HasAuditUsers;
use Database\Factories\OMS\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $goal
 * @property SprintStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $capacity_hours
 * @property string|null $committed_hours
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, ResourceAllocation> $resourceAllocations
 */
#[Fillable([
    'name', 'goal', 'status', 'starts_on', 'ends_on', 'capacity_hours', 'committed_hours',
])]
class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the project the sprint belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks committed to the sprint.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the capacity bookings made for the sprint.
     *
     * @return HasMany<ResourceAllocation, $this>
     */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SprintStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

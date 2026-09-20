<?php

namespace App\Models\OMS;

use App\Enums\MilestoneStatus;
use App\Models\Concerns\HasAuditUsers;
use Database\Factories\OMS\MilestoneFactory;
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
 * @property string|null $description
 * @property MilestoneStatus $status
 * @property Carbon|null $due_on
 * @property int $position
 * @property int $progress_percentage
 * @property bool $is_billable
 * @property string|null $payment_amount
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable([
    'name', 'description', 'status', 'due_on', 'position', 'is_billable', 'payment_amount',
])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the project the milestone belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks scheduled against the milestone.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'due_on' => 'date',
            'is_billable' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}

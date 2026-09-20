<?php

namespace App\Models\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use App\Models\User;
use Database\Factories\OMS\TaskAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property TaskAssignmentRole $role
 * @property TaskAssignmentStatus $status
 * @property string|null $allocated_hours
 * @property string|null $notes
 * @property int|null $assigned_by
 * @property Carbon|null $assigned_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $unassigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Task $task
 * @property-read User $user
 * @property-read User|null $assigner
 */
#[Fillable([
    'user_id', 'role', 'status', 'allocated_hours', 'notes', 'assigned_by', 'assigned_at',
])]
class TaskAssignment extends Model
{
    /** @use HasFactory<TaskAssignmentFactory> */
    use HasFactory;

    /**
     * Get the task being assigned.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the assigned user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who made the assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope the query to assignments that still occupy the assignee.
     *
     * @param  Builder<TaskAssignment>  $query
     * @return Builder<TaskAssignment>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('unassigned_at')->whereIn('status', [
            TaskAssignmentStatus::Assigned->value,
            TaskAssignmentStatus::Accepted->value,
            TaskAssignmentStatus::InProgress->value,
            TaskAssignmentStatus::Submitted->value,
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
            'role' => TaskAssignmentRole::class,
            'status' => TaskAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }
}

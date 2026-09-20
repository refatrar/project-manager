<?php

namespace App\Models\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\TimeLogFactory;
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
 * @property int $team_id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property int|null $todo_item_id
 * @property int|null $meeting_id
 * @property string|null $description
 * @property TimeLogActivityType $activity_type
 * @property TimeLogSource $source
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_minutes
 * @property Carbon $logged_on
 * @property bool $is_billable
 * @property ApprovalStatus $approval_status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $user
 * @property-read Project|null $project
 * @property-read Task|null $task
 * @property-read TodoItem|null $todoItem
 * @property-read Meeting|null $meeting
 * @property-read User|null $approver
 */
#[Fillable([
    'project_id', 'task_id', 'todo_item_id', 'meeting_id', 'description', 'activity_type',
    'source', 'started_at', 'ended_at', 'duration_minutes', 'logged_on', 'is_billable',
])]
class TimeLog extends Model
{
    /** @use HasFactory<TimeLogFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the team the entry is billed to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who logged the time.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project the time was spent on.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task the time was spent on.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the checklist item the time was spent on.
     *
     * @return BelongsTo<TodoItem, $this>
     */
    public function todoItem(): BelongsTo
    {
        return $this->belongsTo(TodoItem::class);
    }

    /**
     * Get the meeting the time was spent in.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the user who approved the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope the query to timers that have not been stopped.
     *
     * @param  Builder<TimeLog>  $query
     * @return Builder<TimeLog>
     */
    #[Scope]
    protected function running(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope the query to entries logged within an inclusive date range.
     *
     * @param  Builder<TimeLog>  $query
     * @return Builder<TimeLog>
     */
    #[Scope]
    protected function loggedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('logged_on', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => TimeLogActivityType::class,
            'source' => TimeLogSource::class,
            'approval_status' => ApprovalStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'logged_on' => 'date',
            'is_billable' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}

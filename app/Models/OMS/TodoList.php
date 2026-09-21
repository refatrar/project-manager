<?php

namespace App\Models\OMS;

use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\TodoListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $owner_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property int|null $meeting_id
 * @property string $name
 * @property string|null $description
 * @property TodoListType $type
 * @property TodoListStatus $status
 * @property Carbon|null $scheduled_for
 * @property int $position
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $owner
 * @property-read Project|null $project
 * @property-read Task|null $task
 * @property-read Meeting|null $meeting
 * @property-read Collection<int, TodoItem> $items
 */
#[Fillable([
    'owner_id', 'project_id', 'task_id', 'meeting_id', 'name', 'description',
    'type', 'status', 'scheduled_for', 'position',
])]
class TodoList extends Model
{
    /** @use HasFactory<TodoListFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the team that owns the list.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user the list belongs to, for personal and daily lists.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the project the list is scoped to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task the list acts as a checklist for.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the meeting the list captures action items for.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the ordered items on the list.
     *
     * @return HasMany<TodoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TodoItem::class)->orderBy('position');
    }

    /**
     * Scope the query to the daily list of the given user for a date.
     *
     * @param  Builder<TodoList>  $query
     * @return Builder<TodoList>
     */
    #[Scope]
    protected function dailyFor(Builder $query, User $user, Carbon $date): Builder
    {
        return $query->where('owner_id', $user->id)
            ->where('type', TodoListType::Daily->value)
            ->whereDate('scheduled_for', $date);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TodoListType::class,
            'status' => TodoListStatus::class,
            'scheduled_for' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the payload used for the to-do lists page. Assumes `items` is loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'scheduled_for' => $this->scheduled_for?->toDateString(),
            'position' => $this->position,
            'items' => $this->items->map(fn (TodoItem $item): array => $item->toListArray())->all(),
        ];
    }
}

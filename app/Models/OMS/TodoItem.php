<?php

namespace App\Models\OMS;

use App\Enums\Priority;
use App\Models\User;
use Database\Factories\OMS\TodoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $todo_list_id
 * @property int|null $task_id
 * @property int|null $assigned_to
 * @property string $title
 * @property string|null $notes
 * @property Priority $priority
 * @property bool $is_completed
 * @property Carbon|null $due_at
 * @property int|null $estimated_minutes
 * @property int $position
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TodoList $list
 * @property-read Task|null $task
 * @property-read User|null $assignee
 * @property-read User|null $completer
 * @property-read Collection<int, TimeLog> $timeLogs
 */
#[Fillable([
    'task_id', 'assigned_to', 'title', 'notes', 'priority', 'is_completed',
    'due_at', 'estimated_minutes', 'position',
])]
class TodoItem extends Model
{
    /** @use HasFactory<TodoItemFactory> */
    use HasFactory;

    /**
     * Get the list the item belongs to.
     *
     * @return BelongsTo<TodoList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }

    /**
     * Get the task the item was promoted from or links to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user responsible for the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who ticked the item off.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the time logged directly against the item.
     *
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'is_completed' => 'boolean',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

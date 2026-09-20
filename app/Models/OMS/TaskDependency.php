<?php

namespace App\Models\OMS;

use App\Enums\TaskDependencyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int $related_task_id
 * @property TaskDependencyType $type
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Task $task
 * @property-read Task $relatedTask
 * @property-read User|null $creator
 */
#[Fillable(['task_id', 'related_task_id', 'type'])]
class TaskDependency extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the task that declares the dependency.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the task on the other side of the dependency.
     *
     * @return BelongsTo<Task, $this>
     */
    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'related_task_id');
    }

    /**
     * Get the user who created the dependency.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskDependencyType::class,
        ];
    }
}

<?php

namespace App\Models\OMS;

use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property TaskStatus|null $from_status
 * @property TaskStatus $to_status
 * @property string|null $note
 * @property int|null $duration_minutes
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property-read Task $task
 * @property-read User|null $changer
 */
#[Fillable(['from_status', 'to_status', 'note', 'duration_minutes', 'changed_by', 'changed_at'])]
class TaskStatusHistory extends Model
{
    public $timestamps = false;

    /**
     * Get the task the transition belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who changed the status.
     *
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
            'changed_at' => 'datetime',
        ];
    }
}

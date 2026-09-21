<?php

namespace App\Models\OMS;

use App\Models\User;
use Database\Factories\OMS\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int|null $task_id
 * @property int|null $presenter_id
 * @property string $title
 * @property string|null $description
 * @property string|null $notes
 * @property int|null $duration_minutes
 * @property int $position
 * @property bool $is_discussed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read Task|null $task
 * @property-read User|null $presenter
 */
#[Fillable([
    'task_id', 'presenter_id', 'title', 'description', 'notes',
    'duration_minutes', 'position', 'is_discussed',
])]
class MeetingAgendaItem extends Model
{
    /** @use HasFactory<MeetingAgendaItemFactory> */
    use HasFactory;

    /**
     * Get the meeting the agenda item belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the task discussed under this agenda item.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user presenting the agenda item.
     *
     * @return BelongsTo<User, $this>
     */
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_discussed' => 'boolean',
        ];
    }

    /**
     * Get the payload used for the meeting workspace. Assumes `task` and
     * `presenter` are loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'duration_minutes' => $this->duration_minutes,
            'position' => $this->position,
            'is_discussed' => $this->is_discussed,
            'task' => $this->task ? [
                'id' => $this->task->id,
                'reference' => $this->task->reference(),
                'title' => $this->task->title,
                'status' => $this->task->status->value,
            ] : null,
            'presenter' => $this->presenter ? [
                'id' => $this->presenter->id,
                'name' => $this->presenter->name,
            ] : null,
        ];
    }
}

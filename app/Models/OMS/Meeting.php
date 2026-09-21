<?php

namespace App\Models\OMS;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property int|null $sprint_id
 * @property string $title
 * @property MeetingType $type
 * @property MeetingStatus $status
 * @property string|null $agenda
 * @property string|null $minutes
 * @property string|null $decisions
 * @property string|null $location
 * @property string|null $meeting_url
 * @property Carbon $scheduled_start
 * @property Carbon $scheduled_end
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $minutes_published_at
 * @property int|null $organized_by
 * @property int|null $recorded_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read Sprint|null $sprint
 * @property-read User|null $organizer
 * @property-read User|null $recorder
 * @property-read Collection<int, MeetingAttendee> $attendees
 * @property-read Collection<int, User> $participants
 * @property-read Collection<int, MeetingAgendaItem> $agendaItems
 * @property-read Collection<int, TodoList> $actionItemLists
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, TimeLog> $timeLogs
 */
#[Fillable([
    'project_id', 'sprint_id', 'title', 'type', 'status', 'agenda', 'minutes', 'decisions',
    'location', 'meeting_url', 'scheduled_start', 'scheduled_end', 'organized_by', 'recorded_by',
])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the team that owns the meeting.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project the meeting relates to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the sprint the meeting relates to.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the user who organized the meeting.
     *
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organized_by');
    }

    /**
     * Get the user who recorded the minutes.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the attendee records for the meeting.
     *
     * @return HasMany<MeetingAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    /**
     * Get the registered users invited to the meeting.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')
            ->withPivot(['role', 'attendance_status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Get the ordered agenda items of the meeting.
     *
     * @return HasMany<MeetingAgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('position');
    }

    /**
     * Get the action item lists produced by the meeting.
     *
     * @return HasMany<TodoList, $this>
     */
    public function actionItemLists(): HasMany
    {
        return $this->hasMany(TodoList::class);
    }

    /**
     * Get the files attached to the meeting.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the time logged against the meeting.
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
            'type' => MeetingType::class,
            'status' => MeetingStatus::class,
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes_published_at' => 'datetime',
        ];
    }

    /**
     * Get the lightweight payload used for the meeting list. Assumes
     * `project` is loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'scheduled_start' => $this->scheduled_start->toIso8601String(),
            'scheduled_end' => $this->scheduled_end->toIso8601String(),
        ];
    }

    /**
     * Get the full payload used for the meeting workspace. Assumes
     * `project`, `organizer` and `recorder` are loaded.
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        return [
            ...$this->toListArray(),
            'sprint_id' => $this->sprint_id,
            'agenda' => $this->agenda,
            'minutes' => $this->minutes,
            'decisions' => $this->decisions,
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'minutes_published_at' => $this->minutes_published_at?->toIso8601String(),
            'organizer' => $this->organizer ? [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
            ] : null,
            'recorder' => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null,
        ];
    }
}

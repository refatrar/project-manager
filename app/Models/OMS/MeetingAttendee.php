<?php

namespace App\Models\OMS;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingAttendeeRole;
use App\Models\User;
use Database\Factories\OMS\MeetingAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property MeetingAttendeeRole $role
 * @property MeetingAttendanceStatus $attendance_status
 * @property Carbon|null $responded_at
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'guest_name', 'guest_email', 'role', 'attendance_status',
    'responded_at', 'joined_at', 'left_at',
])]
class MeetingAttendee extends Model
{
    /** @use HasFactory<MeetingAttendeeFactory> */
    use HasFactory;

    /**
     * Get the meeting the attendee belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the registered user, when the attendee is not an external guest.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MeetingAttendeeRole::class,
            'attendance_status' => MeetingAttendanceStatus::class,
            'responded_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * Get the payload used for the meeting workspace. Assumes `user` is loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'role' => $this->role->value,
            'attendance_status' => $this->attendance_status->value,
        ];
    }
}

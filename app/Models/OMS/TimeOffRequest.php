<?php

namespace App\Models\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeOffType;
use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\TimeOffRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Approved time off is subtracted from a user's scheduled capacity.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property TimeOffType $type
 * @property ApprovalStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_full_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $total_hours
 * @property string|null $reason
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $decision_note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Team|null $team
 * @property-read User|null $approver
 */
#[Fillable([
    'team_id', 'type', 'status', 'starts_on', 'ends_on', 'is_full_day',
    'start_time', 'end_time', 'total_hours', 'reason',
])]
class TimeOffRequest extends Model
{
    /** @use HasFactory<TimeOffRequestFactory> */
    use HasFactory;

    /**
     * Get the user taking the time off.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team the request was filed in.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who approved the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope the query to approved requests overlapping an inclusive date range.
     *
     * @param  Builder<TimeOffRequest>  $query
     * @return Builder<TimeOffRequest>
     */
    #[Scope]
    protected function approvedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('status', ApprovalStatus::Approved->value)
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TimeOffType::class,
            'status' => ApprovalStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_full_day' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the payload used for the time-off page. Assumes `user` and
     * `approver` are loaded.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'type' => $this->type->value,
            'status' => $this->status->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'is_full_day' => $this->is_full_day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_hours' => $this->total_hours !== null ? (float) $this->total_hours : null,
            'reason' => $this->reason,
            'approver' => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
        ];
    }
}

<?php

namespace App\Models\OMS;

use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\UserWorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The recurring weekly capacity of a user, used as the baseline for availability.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property int $day_of_week
 * @property bool $is_working_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property string $capacity_hours
 * @property string|null $timezone
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Team|null $team
 */
#[Fillable([
    'team_id', 'day_of_week', 'is_working_day', 'start_time', 'end_time', 'break_minutes',
    'capacity_hours', 'timezone', 'effective_from', 'effective_until',
])]
class UserWorkSchedule extends Model
{
    /** @use HasFactory<UserWorkScheduleFactory> */
    use HasFactory;

    /**
     * Get the user the schedule belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team the schedule applies to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope the query to the schedule rows in force on the given date.
     *
     * @param  Builder<UserWorkSchedule>  $query
     * @return Builder<UserWorkSchedule>
     */
    #[Scope]
    protected function effectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $date->toDateString()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_working_day' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * Get the payload used for the work schedule page.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'is_working_day' => $this->is_working_day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'capacity_hours' => (float) $this->capacity_hours,
        ];
    }
}

<?php

namespace App\Models\OMS;

use Database\Factories\OMS\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The platform's single recurring weekly capacity template, used as the
 * baseline for every user's availability (replaces the former per-user
 * `UserWorkSchedule`).
 *
 * @property int $id
 * @property int $day_of_week
 * @property bool $is_working_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property string $capacity_hours
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'day_of_week', 'is_working_day', 'start_time', 'end_time', 'break_minutes',
    'capacity_hours', 'effective_from', 'effective_until',
])]
class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    /**
     * Scope the query to the schedule rows in force on the given date.
     *
     * @param  Builder<WorkSchedule>  $query
     * @return Builder<WorkSchedule>
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

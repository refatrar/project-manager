<?php

namespace App\Actions\OMS;

use App\Models\OMS\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SetWorkSchedule
{
    /**
     * Replace the platform's global weekly work schedule with a new version
     * effective from the given date, versioned rather than overwritten in
     * place so a past change stays historically accurate (RD.md FR-8.1).
     *
     * Any row that is not a working day is normalized here — its times,
     * break and capacity are forced to empty/zero regardless of what was
     * submitted, so `capacity(day)` (DESIGN.md 3.6) can never read a stray
     * non-zero value for a day nobody works.
     *
     * A submission whose `effective_from` matches an existing version
     * (typically: correcting today's edit a second time) replaces that
     * version in place rather than end-dating it the day before itself,
     * which would otherwise leave a same-day gap and collide with the
     * `(day_of_week, effective_from)` unique constraint on re-insert. Only
     * a still-open version that started *earlier* is end-dated.
     *
     * @param  array<int, array{day_of_week: int, is_working_day: bool, start_time: ?string, end_time: ?string, break_minutes: int, capacity_hours: float}>  $days
     * @return Collection<int, WorkSchedule>
     */
    public function handle(Carbon $effectiveFrom, array $days): Collection
    {
        return DB::transaction(function () use ($effectiveFrom, $days) {
            WorkSchedule::query()
                ->where('effective_from', $effectiveFrom->toDateString())
                ->delete();

            WorkSchedule::query()
                ->whereNull('effective_until')
                ->where('effective_from', '<', $effectiveFrom->toDateString())
                ->update(['effective_until' => $effectiveFrom->copy()->subDay()->toDateString()]);

            return collect($days)->map(function (array $day) use ($effectiveFrom): WorkSchedule {
                $isWorkingDay = $day['is_working_day'];

                return WorkSchedule::query()->create([
                    'day_of_week' => $day['day_of_week'],
                    'is_working_day' => $isWorkingDay,
                    'start_time' => $isWorkingDay ? $day['start_time'] : null,
                    'end_time' => $isWorkingDay ? $day['end_time'] : null,
                    'break_minutes' => $isWorkingDay ? $day['break_minutes'] : 0,
                    'capacity_hours' => $isWorkingDay ? $day['capacity_hours'] : 0,
                    'effective_from' => $effectiveFrom->toDateString(),
                ]);
            })->values();
        });
    }
}

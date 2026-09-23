<?php

namespace App\Actions\OMS;

use App\Data\AvailabilityDayData;
use App\Models\OMS\Holiday;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\WorkSchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalculateUserAvailability
{
    /**
     * Answer, for a user and an inclusive date range: scheduled capacity,
     * occupied hours and remaining free hours per day (RD.md FR-8.4),
     * following the three-table formula in DESIGN.md 3.6:
     *
     *   capacity(day)    = the global work schedule version in force that
     *                      day — zero outright if the date is a public
     *                      holiday, regardless of what the weekly template says
     *   occupied(day)    = sum of planned/confirmed resource_allocations covering that day
     *   unavailable(day) = approved time off covering that day — the whole day's
     *                      capacity if full-day, otherwise its own recorded hours
     *   available(day)   = capacity − occupied − unavailable, floored at zero
     *
     * No column stores "available hours" anywhere — it's derived fresh
     * from the source tables every time, never cached on the user. The
     * schedule and holiday calendar are platform-wide, so this is the same
     * for every user; only `occupied`/`unavailable` vary per user.
     *
     * `$scopeToProject`, when given, counts only *that* project's own
     * bookings toward "occupied" (RD.md FR-8.8: a project manager may see
     * a member's capacity in the context of their shared project, but
     * never the hours, task or existence of that member's bookings on a
     * project the manager has no visibility into). Time off stays global
     * either way — it isn't tied to any project, so it isn't the
     * confidential "Project B" detail FR-8.8 is about.
     *
     * @return Collection<int, AvailabilityDayData>
     */
    public function handle(User $user, Carbon $from, Carbon $to, ?Project $scopeToProject = null): Collection
    {
        $schedules = WorkSchedule::query()
            ->where('effective_from', '<=', $to->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $from->toDateString()))
            ->get();

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn (CarbonInterface $date): string => $date->toDateString())
            ->all();

        $allocations = ResourceAllocation::query()
            ->where('user_id', $user->id)
            ->when($scopeToProject !== null, fn ($query) => $query->where('project_id', $scopeToProject->id))
            ->reservingBetween($from, $to)
            ->get(['starts_on', 'ends_on', 'hours_per_day']);

        $timeOff = TimeOffRequest::query()
            ->where('user_id', $user->id)
            ->approvedBetween($from, $to)
            ->get(['starts_on', 'ends_on', 'is_full_day', 'total_hours']);

        $days = collect();

        for ($date = $from->copy(); $date->lte($to); $date = $date->copy()->addDay()) {
            $schedule = $schedules->first(fn (WorkSchedule $row): bool => $row->day_of_week === $date->dayOfWeekIso
                && $row->effective_from->lte($date)
                && ($row->effective_until === null || $row->effective_until->gte($date)));

            $isHoliday = in_array($date->toDateString(), $holidays, true);

            $capacity = ! $isHoliday && $schedule !== null && $schedule->is_working_day ? (float) $schedule->capacity_hours : 0.0;

            $occupied = (float) $allocations
                ->filter(fn (ResourceAllocation $allocation): bool => $allocation->starts_on->lte($date) && $allocation->ends_on->gte($date))
                ->sum(fn (ResourceAllocation $allocation): float => (float) $allocation->hours_per_day);

            $dayTimeOff = $timeOff->filter(fn (TimeOffRequest $request): bool => $request->starts_on->lte($date) && $request->ends_on->gte($date));

            $unavailable = match (true) {
                $dayTimeOff->isEmpty() => 0.0,
                $dayTimeOff->contains(fn (TimeOffRequest $request): bool => $request->is_full_day) => $capacity,
                default => (float) $dayTimeOff->sum(fn (TimeOffRequest $request): float => (float) $request->total_hours),
            };

            $days->push(new AvailabilityDayData(
                date: $date->toDateString(),
                capacityHours: $capacity,
                occupiedHours: $occupied,
                unavailableHours: $unavailable,
                availableHours: max(0.0, $capacity - $occupied - $unavailable),
            ));
        }

        return $days;
    }
}

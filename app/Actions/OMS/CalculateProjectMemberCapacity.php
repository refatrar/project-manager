<?php

namespace App\Actions\OMS;

use App\Data\AvailabilityDayData;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\UserWorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per active project member: their weekly work-schedule template and their
 * next-14-days availability, with "occupied" counting only bookings on
 * *this* project (RD.md FR-8.8) — never another project's hours, task or
 * even its existence. Meant to be called only after the caller has
 * confirmed the viewer manages this project; it has no authorization
 * logic of its own, same as every other calculation action in this app.
 */
class CalculateProjectMemberCapacity
{
    /**
     * @param  Collection<int, ProjectMember>  $members
     * @return Collection<int, array{user_id: int, schedule: array<int, array{day_of_week: int, is_working_day: bool, capacity_hours: float}>, available_hours_14d: float, days: array<int, array{date: string, capacity_hours: float, occupied_hours: float, available_hours: float}>}>
     */
    public function handle(Project $project, Collection $members, CalculateUserAvailability $calculateUserAvailability): Collection
    {
        $today = Carbon::now();
        $twoWeeksOut = $today->copy()->addDays(13);

        return $members->map(function (ProjectMember $member) use ($project, $today, $twoWeeksOut, $calculateUserAvailability): array {
            $schedule = UserWorkSchedule::query()
                ->where('user_id', $member->user_id)
                ->effectiveOn($today)
                ->orderBy('day_of_week')
                ->get()
                ->map(fn (UserWorkSchedule $row): array => [
                    'day_of_week' => $row->day_of_week,
                    'is_working_day' => $row->is_working_day,
                    'capacity_hours' => (float) $row->capacity_hours,
                ])
                ->values()
                ->all();

            $days = $calculateUserAvailability
                ->handle($member->user, $today->copy(), $twoWeeksOut->copy(), $project)
                ->map(fn (AvailabilityDayData $day): array => [
                    'date' => $day->date,
                    'capacity_hours' => $day->capacityHours,
                    'occupied_hours' => $day->occupiedHours,
                    'available_hours' => $day->availableHours,
                ]);

            return [
                'user_id' => $member->user_id,
                'schedule' => $schedule,
                'available_hours_14d' => round((float) $days->sum('available_hours'), 2),
                'days' => $days->values()->all(),
            ];
        })->values();
    }
}

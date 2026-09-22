<?php

namespace App\Actions\OMS;

use App\Data\AvailabilityDayData;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FindAvailableUsers
{
    public function __construct(
        private readonly CalculateUserAvailability $calculateUserAvailability,
    ) {
        //
    }

    /**
     * Answer, for a required number of hours per day and a date range,
     * which of the given candidate users have enough free capacity on
     * every day they're actually scheduled to work (RD.md FR-8.5).
     *
     * A day the user isn't scheduled to work at all (zero capacity — a
     * weekend, most commonly) is skipped rather than counted as a
     * failure: the question is "can this person cover the days they'd
     * actually be working", not "are they free every single calendar
     * day including days off". A candidate with no working days at all
     * in the range has nothing to offer and is excluded.
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>
     */
    public function handle(Collection $candidates, Carbon $from, Carbon $to, float $requiredHoursPerDay): Collection
    {
        return $candidates->filter(function (User $user) use ($from, $to, $requiredHoursPerDay): bool {
            $workingDays = $this->calculateUserAvailability
                ->handle($user, $from, $to)
                ->filter(fn (AvailabilityDayData $day): bool => $day->capacityHours > 0);

            if ($workingDays->isEmpty()) {
                return false;
            }

            return $workingDays->every(fn (AvailabilityDayData $day): bool => $day->availableHours >= $requiredHoursPerDay);
        })->values();
    }
}

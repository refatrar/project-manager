<?php

namespace App\Actions\OMS;

use App\Models\OMS\ResourceAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Flags which bookings in a set put their user over capacity on at least
 * one day of the booking's own range — RD.md FR-8.9: a manager scheduling
 * someone must see that a conflict exists, even when the reason is a
 * booking on a project they have no visibility into (FR-8.8). The flag
 * itself is safe to show anyone who can already see these bookings; it
 * never names which other booking, project or task is responsible, only
 * that `CalculateUserAvailability`'s all-projects occupancy exceeded
 * capacity on some day. See docs/architecture-decisions.md's FR-8.9 entry.
 */
class DetectOverAllocatedBookings
{
    /**
     * @param  Collection<int, ResourceAllocation>  $allocations
     * @return array<int, bool> keyed by allocation id
     */
    public function handle(Collection $allocations, CalculateUserAvailability $calculateUserAvailability): array
    {
        $flags = [];

        foreach ($allocations->groupBy('user_id') as $userAllocations) {
            /** @var ResourceAllocation $first */
            $first = $userAllocations->first();

            // `starts_on`/`ends_on` are `CarbonImmutable` (the app-wide
            // `Date::use(CarbonImmutable::class)` default), but this action
            // type-hints the mutable `Carbon` — `Carbon::parse()` converts.
            $days = $calculateUserAvailability
                ->handle($first->user, Carbon::parse($userAllocations->pluck('starts_on')->min()), Carbon::parse($userAllocations->pluck('ends_on')->max()))
                ->keyBy('date');

            foreach ($userAllocations as $allocation) {
                $flags[$allocation->id] = false;

                // `$date` is `CarbonImmutable` too, so `addDay()` alone
                // would return a new instance and leave `$date` unchanged
                // forever — the increment must reassign.
                for ($date = $allocation->starts_on->copy(); $date->lte($allocation->ends_on); $date = $date->copy()->addDay()) {
                    $day = $days->get($date->toDateString());

                    if ($day !== null && ($day->occupiedHours + $day->unavailableHours) > $day->capacityHours) {
                        $flags[$allocation->id] = true;

                        break;
                    }
                }
            }
        }

        return $flags;
    }
}

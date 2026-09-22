<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\CalculateUserAvailability;
use App\Data\AvailabilityDayData;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TeamCapacityController extends Controller
{
    /**
     * A two-week occupancy heatmap across every team member (RD.md FR-8.6),
     * built from the same `CalculateUserAvailability` action the personal
     * availability check and the timesheet already use — no separate
     * capacity computation to keep in sync. Restricted to a team admin,
     * the same wide-visibility threshold as `FindAvailableUsersController`
     * and the other cross-member capacity screens.
     */
    public function index(Request $request, Team $current_team, CalculateUserAvailability $calculateUserAvailability): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $role = $user->teamRole($current_team);
        abort_unless($role !== null && $role->isAtLeast(TeamRole::Admin), 403);

        $from = $this->rangeStart($request);
        $to = $from->copy()->addDays(13);

        $members = $current_team->members()->orderBy('name')->get();

        $rows = $members
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'days' => $calculateUserAvailability
                    ->handle($member, $from->copy(), $to->copy())
                    ->map(fn (AvailabilityDayData $day): array => [
                        'date' => $day->date,
                        'capacity_hours' => $day->capacityHours,
                        'occupied_hours' => $day->occupiedHours,
                        'unavailable_hours' => $day->unavailableHours,
                        'available_hours' => $day->availableHours,
                    ])
                    ->values(),
            ])
            ->values();

        return Inertia::render('team-capacity/index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'members' => $rows,
        ]);
    }

    /**
     * Resolve the Monday of the requested (or current) two-week window.
     */
    private function rangeStart(Request $request): Carbon
    {
        $from = $request->string('from')->isNotEmpty() ? Carbon::parse($request->string('from')->value()) : Carbon::now();

        return $from->startOfWeek(Carbon::MONDAY);
    }
}

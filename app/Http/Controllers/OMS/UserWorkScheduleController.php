<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\CalculateUserAvailability;
use App\Actions\OMS\SetUserWorkSchedule;
use App\Data\AvailabilityDayData;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveUserWorkScheduleRequest;
use App\Models\OMS\UserWorkSchedule;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UserWorkScheduleController extends Controller
{
    /**
     * Display the acting user's own weekly work schedule and its history
     * (RD.md FR-8.1), plus a 14-day availability preview (FR-8.4) — the
     * most natural place to surface `CalculateUserAvailability` for now,
     * since it's the same "my own capacity" page. Always scoped to the
     * user, never a route-bound model — a schedule has no independent
     * existence to authorize against, so there is no dedicated policy
     * here, same reasoning as `MyDayController`.
     */
    public function index(Request $request, Team $current_team, CalculateUserAvailability $calculateUserAvailability): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $today = Carbon::today();
        $availability = $calculateUserAvailability
            ->handle($user, $today, $today->copy()->addDays(13))
            ->map(fn (AvailabilityDayData $day): array => [
                'date' => $day->date,
                'capacity_hours' => $day->capacityHours,
                'occupied_hours' => $day->occupiedHours,
                'unavailable_hours' => $day->unavailableHours,
                'available_hours' => $day->availableHours,
            ]);

        return Inertia::render('work-schedule/index', [
            'versions' => $this->versions($user->id),
            'availability' => $availability,
        ]);
    }

    /**
     * Replace the user's weekly schedule with a new version, effective
     * from the given date.
     */
    public function store(SaveUserWorkScheduleRequest $request, Team $current_team, SetUserWorkSchedule $setUserWorkSchedule): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $setUserWorkSchedule->handle(
            $user,
            $current_team,
            Carbon::parse($request->validated('effective_from')),
            $request->validated('days'),
        );

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Work schedule updated.')]);

            return to_route('work-schedule.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'versions' => $this->versions($user->id),
            'message' => __('Work schedule updated.'),
        ], 201);
    }

    /**
     * Group the user's schedule rows into versions by shared
     * `effective_from`, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function versions(int $userId): array
    {
        $today = Carbon::today()->toDateString();

        return UserWorkSchedule::query()
            ->where('user_id', $userId)
            ->orderByDesc('effective_from')
            ->orderBy('day_of_week')
            ->get()
            ->groupBy(fn (UserWorkSchedule $row): string => $row->effective_from->toDateString())
            ->map(function (Collection $days, string $effectiveFrom) use ($today): array {
                $effectiveUntil = $days->first()->effective_until?->toDateString();

                return [
                    'effective_from' => $effectiveFrom,
                    'effective_until' => $effectiveUntil,
                    'is_current' => $effectiveFrom <= $today && ($effectiveUntil === null || $effectiveUntil >= $today),
                    'days' => $days->sortBy('day_of_week')->values()->map(fn (UserWorkSchedule $day): array => $day->toListArray())->all(),
                ];
            })
            ->sortByDesc('effective_from')
            ->values()
            ->all();
    }
}

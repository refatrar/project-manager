<?php

namespace App\Http\Controllers\Admin;

use App\Actions\OMS\CalculateUserAvailability;
use App\Actions\OMS\SetUserWorkSchedule;
use App\Data\AvailabilityDayData;
use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWorkScheduleRequest;
use App\Models\Admin;
use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admins set each user's weekly capacity template (RD.md FR-8.1).
 * The schedule still belongs to the user — availability, bookings and
 * timesheets keep reading `user_work_schedules` — but editing it is no
 * longer a self-service page on the team app.
 */
class WorkScheduleController extends Controller
{
    /**
     * List users and, when one is selected, their schedule versions and a
     * 14-day availability preview.
     */
    public function index(Request $request, CalculateUserAvailability $calculateUserAvailability): Response
    {
        $this->authorizeManageWorkSchedules($request);

        $selected = $request->filled('user')
            ? User::query()->find($request->integer('user'))
            : null;

        $today = Carbon::today();
        $availability = $selected === null
            ? []
            : $calculateUserAvailability
                ->handle($selected, $today, $today->copy()->addDays(13))
                ->map(fn (AvailabilityDayData $day): array => [
                    'date' => $day->date,
                    'capacity_hours' => $day->capacityHours,
                    'occupied_hours' => $day->occupiedHours,
                    'unavailable_hours' => $day->unavailableHours,
                    'available_hours' => $day->availableHours,
                ]);

        return Inertia::render('admin/work-schedules/index', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => $user->only(['id', 'name', 'email']))
                ->values()
                ->all(),
            'selectedUserId' => $selected?->id,
            'versions' => $selected === null ? [] : $this->versions($selected->id),
            'availability' => $availability,
        ]);
    }

    /**
     * Replace the selected user's weekly schedule with a new version,
     * effective from the given date. The user's current team is recorded
     * on the rows when they have one.
     */
    public function store(SaveWorkScheduleRequest $request, SetUserWorkSchedule $setUserWorkSchedule): JsonResponse|RedirectResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));

        $setUserWorkSchedule->handle(
            $user,
            $user->currentTeam,
            Carbon::parse($request->validated('effective_from')),
            $request->validated('days'),
        );

        $message = __('Work schedule updated.');

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.work-schedules.index', ['user' => $user->id]);
        }

        return response()->json([
            'versions' => $this->versions($user->id),
            'message' => $message,
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

    private function authorizeManageWorkSchedules(Request $request): void
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->hasPermission(AdminPermission::ManageWorkSchedules->value), 403);
    }
}

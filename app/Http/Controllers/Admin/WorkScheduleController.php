<?php

namespace App\Http\Controllers\Admin;

use App\Actions\OMS\SetWorkSchedule;
use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWorkScheduleRequest;
use App\Models\Admin;
use App\Models\OMS\WorkSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admins set the single, platform-wide weekly capacity template
 * (RD.md FR-8.1) that every user's availability is measured against. There
 * is no per-user override — see TASKS.md 7.10 for the decision to replace
 * the former per-user `user_work_schedules`/`UserWorkSchedule` with this.
 */
class WorkScheduleController extends Controller
{
    /**
     * Show the current version's editor and a history of past versions.
     */
    public function index(Request $request): Response
    {
        $this->authorizeManageWorkSchedules($request);

        return Inertia::render('admin/work-schedules/index', [
            'versions' => $this->versions(),
        ]);
    }

    /**
     * Publish a new version of the global schedule, effective from the
     * given date.
     */
    public function store(SaveWorkScheduleRequest $request, SetWorkSchedule $setWorkSchedule): JsonResponse|RedirectResponse
    {
        $setWorkSchedule->handle(
            Carbon::parse($request->validated('effective_from')),
            $request->validated('days'),
        );

        $message = __('Work schedule updated.');

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.work-schedules.index');
        }

        return response()->json([
            'versions' => $this->versions(),
            'message' => $message,
        ], 201);
    }

    /**
     * Group the schedule rows into versions by shared `effective_from`,
     * newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function versions(): array
    {
        $today = Carbon::today()->toDateString();

        return WorkSchedule::query()
            ->orderByDesc('effective_from')
            ->orderBy('day_of_week')
            ->get()
            ->groupBy(fn (WorkSchedule $row): string => $row->effective_from->toDateString())
            ->map(function (Collection $days, string $effectiveFrom) use ($today): array {
                $effectiveUntil = $days->first()->effective_until?->toDateString();

                return [
                    'effective_from' => $effectiveFrom,
                    'effective_until' => $effectiveUntil,
                    'is_current' => $effectiveFrom <= $today && ($effectiveUntil === null || $effectiveUntil >= $today),
                    'days' => $days->sortBy('day_of_week')->values()->map(fn (WorkSchedule $day): array => $day->toListArray())->all(),
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

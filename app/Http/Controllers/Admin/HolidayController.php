<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveHolidayRequest;
use App\Models\OMS\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform-wide holiday calendar (TASKS.md 7.10): specific dated
 * holidays, entered one year at a time, never a recurring rule. A holiday
 * zeroes availability for every user on that date, regardless of the
 * global work schedule's weekly template — see
 * `CalculateUserAvailability`.
 */
class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $request->filled('year') ? $request->integer('year') : (int) Carbon::today()->format('Y');

        return Inertia::render('admin/holidays/index', [
            'year' => $year,
            'holidays' => Holiday::query()
                ->inYear($year)
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday): array => $holiday->toListArray())
                ->values()
                ->all(),
        ]);
    }

    public function store(SaveHolidayRequest $request): JsonResponse|RedirectResponse
    {
        $holiday = new Holiday($request->validated());
        $holiday->created_by = $request->user('admin')->id;
        $holiday->save();

        return $this->respond($request, __(':name added.', ['name' => $holiday->name]), 201);
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse|RedirectResponse
    {
        $holiday->delete();

        return $this->respond($request, __('Holiday removed.'));
    }

    private function respond(Request $request, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.holidays.index');
        }

        return response()->json(['message' => $message], $status);
    }
}

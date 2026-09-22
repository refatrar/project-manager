<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\SubmitTimesheet;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller
{
    /**
     * Display the acting user's own timesheet: the selected week's time
     * logs as a grid — one row per (project, activity) combination
     * worked, one column per day — plus a submit action (RD.md: "a
     * timesheet for a period" is just `time_logs` grouped by user and
     * date range, DESIGN.md 3.11; there is no separate timesheet table).
     * Self-scoped like the other capacity pages; the approval side is a
     * separate, not-yet-built screen for the project manager.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $weekStart = $this->weekStart($request);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $logs = TimeLog::query()
            ->where('team_id', $current_team->id)
            ->where('user_id', $user->id)
            ->whereBetween('logged_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('project:id,code,name')
            ->get();

        return Inertia::render('timesheet/index', [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'days' => $this->weekDates($weekStart),
            'rows' => $this->rows($logs, $weekStart),
            'dayTotals' => $this->dayTotals($logs, $weekStart),
            'weekTotal' => round($logs->sum('duration_minutes') / 60, 2),
            'statusCounts' => [
                'pending' => $logs->where('approval_status', ApprovalStatus::Pending)->count(),
                'submitted' => $logs->where('approval_status', ApprovalStatus::Submitted)->count(),
                'approved' => $logs->where('approval_status', ApprovalStatus::Approved)->count(),
                'rejected' => $logs->where('approval_status', ApprovalStatus::Rejected)->count(),
            ],
            'canSubmit' => $logs->contains(fn (TimeLog $log): bool => $log->approval_status === ApprovalStatus::Pending && $log->ended_at !== null),
            'hasRunningTimer' => $logs->contains(fn (TimeLog $log): bool => $log->ended_at === null),
        ]);
    }

    /**
     * Submit the selected week's finished, pending entries for approval.
     */
    public function submit(Request $request, Team $current_team, SubmitTimesheet $submitTimesheet): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $weekStart = $this->weekStart($request);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $count = $submitTimesheet->handle($user, $current_team, $weekStart, $weekEnd);

        $message = $count > 0
            ? trans_choice(':count entry submitted.|:count entries submitted.', $count, ['count' => $count])
            : __('Nothing to submit for this week.');

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('timesheet.index', ['current_team' => $current_team->slug, 'week' => $weekStart->toDateString()]);
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Resolve the Monday of the requested (or current) week.
     */
    private function weekStart(Request $request): Carbon
    {
        $week = $request->string('week')->isNotEmpty() ? Carbon::parse($request->string('week')->value()) : Carbon::now();

        return $week->startOfWeek(Carbon::MONDAY);
    }

    /**
     * @return array<int, string>
     */
    private function weekDates(Carbon $weekStart): array
    {
        return collect(range(0, 6))
            ->map(fn (int $offset): string => $weekStart->copy()->addDays($offset)->toDateString())
            ->all();
    }

    /**
     * Group the week's logs into one row per (project, activity type),
     * with each day's hours and a row total.
     *
     * @param  Collection<int, TimeLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function rows(Collection $logs, Carbon $weekStart): array
    {
        $dates = $this->weekDates($weekStart);

        return $logs
            ->groupBy(fn (TimeLog $log): string => ($log->project_id ?? 'none').'|'.$log->activity_type->value)
            ->map(function (Collection $group) use ($dates): array {
                /** @var TimeLog $first */
                $first = $group->first();

                $days = collect($dates)->mapWithKeys(fn (string $date): array => [
                    $date => round($group->filter(fn (TimeLog $log): bool => $log->logged_on->toDateString() === $date)->sum('duration_minutes') / 60, 2),
                ])->all();

                return [
                    'project' => $first->project ? [
                        'id' => $first->project->id,
                        'code' => $first->project->code,
                        'name' => $first->project->name,
                    ] : null,
                    'activity_type' => $first->activity_type->value,
                    'days' => $days,
                    'total' => round($group->sum('duration_minutes') / 60, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TimeLog>  $logs
     * @return array<string, float>
     */
    private function dayTotals(Collection $logs, Carbon $weekStart): array
    {
        return collect($this->weekDates($weekStart))->mapWithKeys(fn (string $date): array => [
            $date => round($logs->filter(fn (TimeLog $log): bool => $log->logged_on->toDateString() === $date)->sum('duration_minutes') / 60, 2),
        ])->all();
    }
}

<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\StartTimeLog;
use App\Actions\OMS\StopTimeLog;
use App\Enums\ApprovalStatus;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveTimeLogRequest;
use App\Http\Requests\OMS\StartTimeLogRequest;
use App\Models\OMS\Project;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TimeLogController extends Controller
{
    /**
     * Display the acting user's own time logs from the last 30 days, plus
     * their running timer if any (RD.md FR-8.6, FR-8.7). Self-scoped, no
     * team-admin override — same reasoning as the work schedule and
     * time-off pages; a manager acting on someone else's logs belongs to
     * the not-yet-built timesheet approval screen, not this one.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [TimeLog::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $logs = TimeLog::query()
            ->where('team_id', $current_team->id)
            ->where('user_id', $user->id)
            ->where('logged_on', '>=', Carbon::today()->subDays(30)->toDateString())
            ->with(['user:id,name', 'project:id,code,name', 'task.project:id,code'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (TimeLog $timeLog): array => $timeLog->toListArray());

        $runningTimeLog = TimeLog::query()
            ->where('team_id', $current_team->id)
            ->where('user_id', $user->id)
            ->running()
            ->with(['user:id,name', 'project:id,code,name', 'task.project:id,code'])
            ->first();

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->forMember($user)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('time-logs/index', [
            'logs' => $logs,
            'running' => $runningTimeLog?->toListArray(),
            'projects' => $projects,
            'activityTypeOptions' => TimeLogActivityType::options(),
        ]);
    }

    /**
     * Start a running timer for the user.
     */
    public function start(StartTimeLogRequest $request, Team $current_team, StartTimeLog $startTimeLog): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [TimeLog::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        try {
            $startTimeLog->handle($user, $current_team, $request->safe()->only([
                'project_id', 'task_id', 'activity_type', 'description',
            ]));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->savedResponse($request, $current_team, __('Timer started.'), 201);
    }

    /**
     * Stop the user's running timer.
     */
    public function stop(Request $request, Team $current_team, TimeLog $time_log, StopTimeLog $stopTimeLog): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_log);
        Gate::authorize('stop', $time_log);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        try {
            $stopTimeLog->handle($time_log, $user);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->savedResponse($request, $current_team, __('Timer stopped.'));
    }

    /**
     * Record a complete, already-finished entry (manual entry, RD.md FR-8.7).
     */
    public function store(SaveTimeLogRequest $request, Team $current_team): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [TimeLog::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $timeLog = new TimeLog($request->safe()->only([
            'project_id', 'task_id', 'activity_type', 'description', 'is_billable', 'started_at', 'ended_at',
        ]));
        $timeLog->team_id = $current_team->id;
        $timeLog->user_id = $user->id;
        $timeLog->source = TimeLogSource::Manual;
        $timeLog->approval_status = ApprovalStatus::Pending;
        $timeLog->logged_on = Carbon::parse($request->validated('started_at'));
        $timeLog->duration_minutes = $this->durationMinutes($request);
        $timeLog->created_by = $user->id;
        $timeLog->save();

        return $this->savedResponse($request, $current_team, __('Time logged.'), 201);
    }

    /**
     * Update the specified entry — only reachable while pending
     * (`TimeLogPolicy::update`).
     */
    public function update(SaveTimeLogRequest $request, Team $current_team, TimeLog $time_log): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_log);
        Gate::authorize('update', $time_log);

        $time_log->fill($request->safe()->only([
            'project_id', 'task_id', 'activity_type', 'description', 'is_billable', 'started_at', 'ended_at',
        ]));
        $time_log->logged_on = Carbon::parse($request->validated('started_at'));
        $time_log->duration_minutes = $this->durationMinutes($request);
        $time_log->save();

        return $this->savedResponse($request, $current_team, __('Time log updated.'));
    }

    /**
     * Delete the specified entry — only reachable while pending.
     */
    public function destroy(Request $request, Team $current_team, TimeLog $time_log): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_log);
        Gate::authorize('delete', $time_log);

        $time_log->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Time log deleted.')]);

            return to_route('time-logs.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('Time log deleted.'),
        ]);
    }

    /**
     * Compute the entry's duration in whole minutes from its own
     * `started_at`/`ended_at` — never trusted from the client.
     */
    private function durationMinutes(SaveTimeLogRequest $request): int
    {
        $start = Carbon::parse($request->validated('started_at'));
        $end = Carbon::parse($request->validated('ended_at'));

        return max(1, (int) $start->diffInMinutes($end));
    }

    /**
     * An entry scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeOnTeam(Team $current_team, TimeLog $timeLog): void
    {
        abort_unless($timeLog->team_id === $current_team->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('time-logs.index', ['current_team' => $team->slug]);
        }

        return response()->json([
            'message' => $message,
        ], $status);
    }
}

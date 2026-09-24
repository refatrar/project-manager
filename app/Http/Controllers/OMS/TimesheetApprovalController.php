<?php

namespace App\Http\Controllers\OMS;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\DecideTimeLogRequest;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetApprovalController extends Controller
{
    /**
     * Display every submitted time log the acting user may decide on —
     * filtered through `TimeLogPolicy::decide` itself, so the queue and
     * the decide action can never disagree (Project Leads, project
     * managers, `projects.manage-all`, and `timesheet-approvals.decide`
     * holders each see exactly what they can act on, never their own).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $entries = TimeLog::query()
            ->where('team_id', $current_team->id)
            ->where('approval_status', ApprovalStatus::Submitted->value)
            ->where('user_id', '!=', $user->id)
            ->with(['user:id,name', 'project', 'task:id,number,title'])
            ->orderBy('logged_on')
            ->get()
            ->each(function (TimeLog $timeLog) use ($current_team): void {
                $timeLog->setRelation('team', $current_team);
                $timeLog->project?->setRelation('team', $current_team);
            })
            ->filter(fn (TimeLog $timeLog): bool => $user->can('decide', $timeLog))
            ->values()
            ->map(fn (TimeLog $timeLog): array => $timeLog->toListArray());

        return Inertia::render('timesheet-approvals/index', [
            'entries' => $entries,
        ]);
    }

    /**
     * Approve or reject a submitted entry.
     */
    public function decide(DecideTimeLogRequest $request, Team $current_team, TimeLog $time_log): JsonResponse|RedirectResponse
    {
        abort_unless($time_log->team_id === $current_team->id, 404);
        Gate::authorize('decide', $time_log);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $approved = $request->validated('decision') === 'approved';

        $time_log->approval_status = $approved ? ApprovalStatus::Approved : ApprovalStatus::Rejected;
        $time_log->approved_by = $user->id;
        $time_log->approved_at = Carbon::now();
        $time_log->save();

        $message = $approved ? __('Time log approved.') : __('Time log rejected.');

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('timesheet-approvals.index', ['current_team' => $current_team->slug]);
        }

        return response()->json(['message' => $message]);
    }
}

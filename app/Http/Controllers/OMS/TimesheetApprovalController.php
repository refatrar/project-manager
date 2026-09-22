<?php

namespace App\Http\Controllers\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\DecideTimeLogRequest;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
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
     * Display every submitted time log the acting user may decide on: a
     * team admin sees every submitted entry team-wide, a project manager
     * sees only the submitted entries logged against a project they
     * manage — an entry with no project (`project_id` null) is a team
     * admin's to decide, never a project manager's, mirroring
     * `TimeLogPolicy::decide`.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $query = TimeLog::query()
            ->where('team_id', $current_team->id)
            ->where('approval_status', ApprovalStatus::Submitted->value);

        if (! $this->isTeamAdmin($user, $current_team)) {
            $managedProjectIds = ProjectMember::query()
                ->where('user_id', $user->id)
                ->active()
                ->whereIn('role', [
                    ProjectMemberRole::Owner->value,
                    ProjectMemberRole::Manager->value,
                    ProjectMemberRole::Lead->value,
                ])
                ->pluck('project_id');

            $query->whereIn('project_id', $managedProjectIds);
        }

        $entries = $query
            ->with(['user:id,name', 'project:id,code,name', 'task:id,number,title'])
            ->orderBy('logged_on')
            ->get()
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

        $user = $request->user();
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

    private function isTeamAdmin(User $user, Team $team): bool
    {
        $role = $user->teamRole($team);

        return $role !== null && $role->isAtLeast(TeamRole::Admin);
    }
}

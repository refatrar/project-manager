<?php

namespace App\Http\Controllers\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamModulePermission;
use App\Enums\TimeOffType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\DecideTimeOffRequestRequest;
use App\Http\Requests\OMS\SaveTimeOffRequestRequest;
use App\Models\OMS\TimeOffRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffRequestController extends Controller
{
    /**
     * Display the acting user's own requests, plus — for a team admin —
     * the team-wide pending approval queue. Only pending requests are
     * shown for approval, not a full history of every member's decided
     * time off; a broader "everyone's time off" calendar would edge into
     * the still-unscoped FR-8.8/FR-8.9 cross-member visibility questions.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [TimeOffRequest::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $isAdmin = $this->isApprover($user, $current_team);

        $myRequests = TimeOffRequest::query()
            ->where('team_id', $current_team->id)
            ->where('user_id', $user->id)
            ->with(['user:id,name', 'approver:id,name'])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (TimeOffRequest $timeOffRequest): array => $timeOffRequest->toListArray());

        $pendingApprovals = $isAdmin
            ? TimeOffRequest::query()
                ->where('team_id', $current_team->id)
                ->where('status', ApprovalStatus::Pending->value)
                ->with(['user:id,name', 'approver:id,name'])
                ->orderBy('starts_on')
                ->get()
                ->map(fn (TimeOffRequest $timeOffRequest): array => $timeOffRequest->toListArray())
            : [];

        return Inertia::render('time-off/index', [
            'myRequests' => $myRequests,
            'pendingApprovals' => $pendingApprovals,
            'isApprover' => $isAdmin,
            'typeOptions' => TimeOffType::options(),
        ]);
    }

    /**
     * File a new time-off request, always for the acting user and always
     * starting `pending` — the status is never client-settable.
     */
    public function store(SaveTimeOffRequestRequest $request, Team $current_team): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [TimeOffRequest::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $timeOffRequest = new TimeOffRequest($request->safe()->only([
            'type', 'starts_on', 'ends_on', 'is_full_day', 'start_time', 'end_time', 'reason',
        ]));
        $timeOffRequest->team_id = $current_team->id;
        $timeOffRequest->user_id = $user->id;
        $timeOffRequest->status = ApprovalStatus::Pending;
        $timeOffRequest->total_hours = $this->totalHours($request);
        $timeOffRequest->created_by = $user->id;
        $timeOffRequest->save();

        return $this->savedResponse($request, $current_team, __('Time off requested.'), 201);
    }

    /**
     * Update the request's own fields — only reachable while pending
     * (`TimeOffRequestPolicy::update`).
     */
    public function update(SaveTimeOffRequestRequest $request, Team $current_team, TimeOffRequest $time_off_request): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_off_request);
        Gate::authorize('update', $time_off_request);

        $time_off_request->fill($request->safe()->only([
            'type', 'starts_on', 'ends_on', 'is_full_day', 'start_time', 'end_time', 'reason',
        ]));
        $time_off_request->total_hours = $this->totalHours($request);
        $time_off_request->save();

        return $this->savedResponse($request, $current_team, __('Time off request updated.'));
    }

    /**
     * Withdraw a still-pending request. A cancelled request keeps its row
     * (there is no soft-delete column) so it stays visible in the
     * requester's own history rather than vanishing.
     */
    public function cancel(Request $request, Team $current_team, TimeOffRequest $time_off_request): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_off_request);
        Gate::authorize('cancel', $time_off_request);

        $time_off_request->status = ApprovalStatus::Cancelled;
        $time_off_request->save();

        return $this->savedResponse($request, $current_team, __('Time off request cancelled.'));
    }

    /**
     * Approve or reject a pending request. A decision is final in this
     * slice — `TimeOffRequestPolicy::decide` refuses a request that has
     * already been decided, so there is no "undo" path here.
     */
    public function decide(DecideTimeOffRequestRequest $request, Team $current_team, TimeOffRequest $time_off_request): JsonResponse|RedirectResponse
    {
        $this->authorizeOnTeam($current_team, $time_off_request);
        Gate::authorize('decide', $time_off_request);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $approved = $request->validated('decision') === 'approved';

        $time_off_request->status = $approved ? ApprovalStatus::Approved : ApprovalStatus::Rejected;
        $time_off_request->approved_by = $user->id;
        $time_off_request->approved_at = Carbon::now();
        $time_off_request->decision_note = $request->validated('decision_note');
        $time_off_request->save();

        return $this->savedResponse($request, $current_team, $approved ? __('Time off approved.') : __('Time off rejected.'));
    }

    /**
     * Determine whether the user holds approver-level (team admin)
     * standing, mirroring the wide-visibility threshold used across the
     * app (`ProjectPolicy`, `MeetingPolicy`, and so on).
     */
    private function isApprover(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::DecideTimeOff);
    }

    /**
     * Compute the partial-day duration in hours, or null for a full day —
     * `capacity(user, day)` (DESIGN.md 3.6) reads a full day off as the
     * whole day's capacity, so only a partial day needs an explicit hour
     * count.
     */
    private function totalHours(SaveTimeOffRequestRequest $request): ?string
    {
        if ($request->boolean('is_full_day')) {
            return null;
        }

        $start = Carbon::parse($request->validated('start_time'));
        $end = Carbon::parse($request->validated('end_time'));

        return (string) round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * A request scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeOnTeam(Team $current_team, TimeOffRequest $timeOffRequest): void
    {
        abort_unless($timeOffRequest->team_id === $current_team->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('time-off-requests.index', ['current_team' => $team->slug]);
        }

        return response()->json([
            'message' => $message,
        ], $status);
    }
}

<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\GetOrCreateMeetingActionList;
use App\Actions\OMS\ToggleMeetingTimer;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingAttendeeRole;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\TaskTypeStatus;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveMeetingMinutesRequest;
use App\Http\Requests\OMS\SaveMeetingRequest;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAgendaItem;
use App\Models\OMS\MeetingAttendee;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Meetings\MinutesPublished;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MeetingController extends Controller
{
    /**
     * Display a listing of the meetings visible to the user on this team.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Meeting::class, $current_team]);

        $user = $request->user('web');

        $meetings = Meeting::query()
            ->where('team_id', $current_team->id)
            ->when(
                ! $this->hasWideVisibility($request, $current_team),
                fn ($query) => $query->where(function ($query) use ($user) {
                    $query->whereNull('project_id')
                        ->orWhereHas('project.members', fn ($members) => $members
                            ->where('user_id', $user?->id)
                            ->where('status', 'active'));
                }),
            )
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->with('project:id,code,name')
            ->orderByDesc('scheduled_start')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Meeting $meeting): array => $meeting->toListArray());

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->forMember($user)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('meetings/index', [
            'meetings' => $meetings,
            'filters' => $request->only(['status', 'type']),
            'projects' => $projects,
            'statusOptions' => MeetingStatus::options(),
            'typeOptions' => MeetingType::options(),
        ]);
    }

    /**
     * Schedule a newly created meeting.
     */
    public function store(SaveMeetingRequest $request, Team $current_team): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [Meeting::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $meeting = new Meeting($request->safe()->only([
            'project_id', 'sprint_id', 'title', 'type', 'agenda', 'location', 'meeting_url',
            'scheduled_start', 'scheduled_end',
        ]));
        $meeting->team_id = $current_team->id;
        // Set explicitly, not left to the column's DB default: the
        // in-memory model would otherwise have no `status` attribute at
        // all until a fresh reload, and casting null to MeetingStatus blows up.
        $meeting->status = MeetingStatus::Scheduled;
        $meeting->organized_by = $user->id;
        $meeting->created_by = $user->id;
        $meeting->save();

        $meeting->attendees()->create([
            'user_id' => $user->id,
            'role' => 'organizer',
            'attendance_status' => 'accepted',
            'responded_at' => Carbon::now(),
        ]);

        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, __('Meeting scheduled.'), 201);
    }

    /**
     * Display the meeting workspace.
     */
    public function show(Request $request, Team $current_team, Meeting $meeting, GetOrCreateMeetingActionList $getOrCreateMeetingActionList): Response
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('view', $meeting);

        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        $attendees = $meeting->attendees()
            ->with('user:id,name,email')
            ->get()
            ->map(fn (MeetingAttendee $attendee): array => $attendee->toListArray());

        $teamMembers = $current_team->members()->get(['users.id', 'users.name', 'users.email']);

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->forMember($request->user('web'))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $agendaItems = $meeting->agendaItems()
            ->with(['task.project:id,code', 'presenter:id,name'])
            ->get()
            ->map(fn (MeetingAgendaItem $item): array => $item->toListArray());

        // Only a project meeting has a task list to link agenda items
        // against — a team-wide meeting offers nothing here (see
        // `SaveMeetingAgendaItemRequest`).
        $projectTasks = $meeting->project !== null
            ? Task::query()
                ->where('project_id', $meeting->project_id)
                ->orderBy('number')
                ->get(['id', 'number', 'title', 'status'])
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'reference' => "{$meeting->project->code}-{$task->number}",
                    'title' => $task->title,
                    'status' => $task->status->value,
                ])
            : [];

        $actionList = $getOrCreateMeetingActionList->handle($meeting);

        $taskTypes = TaskType::query()
            ->where('status', TaskTypeStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('meetings/show', [
            'meeting' => $meeting->toDetailArray(),
            'attendees' => $attendees,
            'agendaItems' => $agendaItems,
            'actionList' => $actionList->toListArray(),
            'timer' => $this->timerPayload($meeting, $request->user('web')),
            'teamMembers' => $teamMembers,
            'projects' => $projects,
            'projectTasks' => $projectTasks,
            'taskTypes' => $taskTypes,
            'roleOptions' => MeetingAttendeeRole::options(),
            'attendanceStatusOptions' => MeetingAttendanceStatus::options(),
            'typeOptions' => MeetingType::options(),
        ]);
    }

    /**
     * Update the specified meeting's own fields.
     */
    public function update(SaveMeetingRequest $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('update', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $meeting->fill($request->safe()->only([
            'project_id', 'sprint_id', 'title', 'type', 'agenda', 'location', 'meeting_url',
            'scheduled_start', 'scheduled_end',
        ]));
        $meeting->updated_by = $user->id;
        $meeting->save();
        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, __('Meeting updated.'));
    }

    /**
     * Record or edit the meeting's minutes and decisions. Left editable
     * after publishing too — a published minutes doc still needing a
     * typo fixed shouldn't require an "unpublish" step.
     */
    public function updateMinutes(SaveMeetingMinutesRequest $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('recordMinutes', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $meeting->fill($request->safe()->only(['minutes', 'decisions']));
        $meeting->recorded_by = $user->id;
        $meeting->updated_by = $user->id;
        $meeting->save();
        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, __('Minutes saved.'));
    }

    /**
     * Publish the meeting's minutes, stamping `minutes_published_at` the
     * first time only — a later save never re-stamps it.
     */
    public function publishMinutes(Request $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('recordMinutes', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        if ($meeting->minutes_published_at === null) {
            $meeting->minutes_published_at = Carbon::now();
            $meeting->recorded_by = $user->id;
            $meeting->updated_by = $user->id;
            $meeting->save();

            $this->notifyAttendeesOfPublishedMinutes($meeting);
        }

        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, __('Minutes published.'));
    }

    /**
     * Start a timer against the meeting for the acting user (RD.md FR-6.6).
     */
    public function startTimer(Request $request, Team $current_team, Meeting $meeting, ToggleMeetingTimer $toggleMeetingTimer): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('view', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        try {
            $toggleMeetingTimer->start($meeting, $user);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->timerResponse($request, $current_team, $meeting, $user, __('Timer started.'));
    }

    /**
     * Stop the acting user's running timer against the meeting.
     */
    public function stopTimer(Request $request, Team $current_team, Meeting $meeting, ToggleMeetingTimer $toggleMeetingTimer): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('view', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        try {
            $toggleMeetingTimer->stop($meeting, $user);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->timerResponse($request, $current_team, $meeting, $user, __('Timer stopped.'));
    }

    /**
     * Cancel the specified meeting, keeping its history intact.
     */
    public function cancel(Request $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('cancel', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $meeting->status = MeetingStatus::Cancelled;
        $meeting->updated_by = $user->id;
        $meeting->save();
        $meeting->load(['project:id,code,name', 'organizer:id,name', 'recorder:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, __('Meeting cancelled.'));
    }

    /**
     * Soft delete the specified meeting.
     */
    public function destroy(Request $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('delete', $meeting);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $meeting->deleted_by = $user->id;
        $meeting->save();
        $meeting->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting deleted.')]);

            return to_route('meetings.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('Meeting deleted.'),
        ]);
    }

    /**
     * A meeting scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeMeetingOnTeam(Team $current_team, Meeting $meeting): void
    {
        abort_unless($meeting->team_id === $current_team->id, 404);
    }

    /**
     * Determine whether the user's team role grants visibility into every
     * meeting regardless of project membership, mirroring `ProjectPolicy`.
     */
    private function hasWideVisibility(Request $request, Team $team): bool
    {
        $role = $request->user('web')?->teamRole($team);

        return $role !== null && $role->isAtLeast(TeamRole::Admin);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Meeting $meeting, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('meetings.show', ['current_team' => $team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'meeting' => $meeting->toDetailArray(),
            'message' => $message,
        ], $status);
    }

    /**
     * Email every attendee — registered or guest — that minutes have been
     * published. Only called from the first, timestamp-stamping publish;
     * a later idempotent re-publish never re-sends it.
     */
    private function notifyAttendeesOfPublishedMinutes(Meeting $meeting): void
    {
        $meeting->load('team:id,slug');

        $meeting->attendees()->with('user')->get()->each(function (MeetingAttendee $attendee) use ($meeting): void {
            if ($attendee->user !== null) {
                $attendee->user->notify(new MinutesPublished($meeting));
            } elseif ($attendee->guest_email !== null) {
                Notification::route('mail', $attendee->guest_email)->notify(new MinutesPublished($meeting));
            }
        });
    }

    /**
     * Build the timer state shown on the meeting workspace: the acting
     * user's own running entry (if any) and the meeting's total logged
     * minutes across every attendee.
     *
     * @return array<string, mixed>
     */
    private function timerPayload(Meeting $meeting, ?User $user): array
    {
        $runningTimeLog = $user !== null
            ? $meeting->timeLogs()->running()->where('user_id', $user->id)->first()
            : null;

        $totalMinutes = (int) $meeting->timeLogs()->whereNotNull('ended_at')->sum('duration_minutes');

        return [
            'running' => $runningTimeLog ? [
                'id' => $runningTimeLog->id,
                'started_at' => $runningTimeLog->started_at->toIso8601String(),
            ] : null,
            'total_minutes' => $totalMinutes,
        ];
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function timerResponse(Request $request, Team $team, Meeting $meeting, User $user, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('meetings.show', ['current_team' => $team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'timer' => $this->timerPayload($meeting, $user),
            'message' => $message,
        ]);
    }
}

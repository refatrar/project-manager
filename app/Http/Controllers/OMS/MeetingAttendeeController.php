<?php

namespace App\Http\Controllers\OMS;

use App\Enums\MeetingAttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveMeetingAttendeeRequest;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAttendee;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MeetingAttendeeController extends Controller
{
    /**
     * Invite an attendee to the meeting. Always starts "invited" — the
     * outcome is recorded later, by the attendee's own RSVP or by the
     * organizer marking attendance (both go through `update`).
     */
    public function store(SaveMeetingAttendeeRequest $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $attendee = new MeetingAttendee($request->safe()->only(['user_id', 'guest_name', 'guest_email', 'role']));
        $attendee->meeting_id = $meeting->id;
        $attendee->attendance_status = MeetingAttendanceStatus::Invited;
        $attendee->save();
        $attendee->load('user:id,name,email');

        return $this->savedResponse($request, $current_team, $meeting, $attendee, __('Attendee invited.'), 201);
    }

    /**
     * Update the attendee's role, RSVP or attendance outcome, stamping
     * `responded_at` / `joined_at` the first time each is reached.
     */
    public function update(SaveMeetingAttendeeRequest $request, Team $current_team, Meeting $meeting, MeetingAttendee $attendee): JsonResponse|RedirectResponse
    {
        $this->authorizeAttendeeOnMeeting($current_team, $meeting, $attendee);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $attendee->role = $request->safe()->only(['role'])['role'];

        $newStatus = MeetingAttendanceStatus::from($request->validated('attendance_status'));
        $isRsvp = in_array($newStatus, [
            MeetingAttendanceStatus::Accepted,
            MeetingAttendanceStatus::Declined,
            MeetingAttendanceStatus::Tentative,
        ], true);

        if ($isRsvp && $attendee->responded_at === null) {
            $attendee->responded_at = Carbon::now();
        }

        if ($newStatus === MeetingAttendanceStatus::Attended && $attendee->joined_at === null) {
            $attendee->joined_at = Carbon::now();
        }

        $attendee->attendance_status = $newStatus;
        $attendee->save();
        $attendee->load('user:id,name,email');

        return $this->savedResponse($request, $current_team, $meeting, $attendee, __('Attendee updated.'));
    }

    /**
     * Remove the attendee from the meeting.
     */
    public function destroy(Request $request, Team $current_team, Meeting $meeting, MeetingAttendee $attendee): JsonResponse|RedirectResponse
    {
        $this->authorizeAttendeeOnMeeting($current_team, $meeting, $attendee);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $attendee->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendee removed.')]);

            return to_route('meetings.show', ['current_team' => $current_team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'message' => __('Attendee removed.'),
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
     * An attendee scoped by the URL's meeting and team, or a 404 (NFR-1).
     */
    private function authorizeAttendeeOnMeeting(Team $current_team, Meeting $meeting, MeetingAttendee $attendee): void
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);

        abort_unless($attendee->meeting_id === $meeting->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Meeting $meeting, MeetingAttendee $attendee, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('meetings.show', ['current_team' => $team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'attendee' => $attendee->toListArray(),
            'message' => $message,
        ], $status);
    }
}

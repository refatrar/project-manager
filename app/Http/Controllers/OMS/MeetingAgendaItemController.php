<?php

namespace App\Http\Controllers\OMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\MoveMeetingAgendaItemRequest;
use App\Http\Requests\OMS\SaveMeetingAgendaItemRequest;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAgendaItem;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MeetingAgendaItemController extends Controller
{
    /**
     * Add an agenda item to the meeting, at the end of the current order.
     */
    public function store(SaveMeetingAgendaItemRequest $request, Team $current_team, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $item = new MeetingAgendaItem($request->safe()->only([
            'title', 'description', 'notes', 'duration_minutes', 'task_id', 'presenter_id',
        ]));
        $item->meeting_id = $meeting->id;
        $item->position = $meeting->agendaItems()->max('position') + 1;
        $item->save();
        $item->load(['task.project:id,code', 'presenter:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, $item, __('Agenda item added.'), 201);
    }

    /**
     * Update the agenda item's own fields.
     */
    public function update(SaveMeetingAgendaItemRequest $request, Team $current_team, Meeting $meeting, MeetingAgendaItem $item): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnMeeting($current_team, $meeting, $item);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $item->fill($request->safe()->only([
            'title', 'description', 'notes', 'duration_minutes', 'task_id', 'presenter_id', 'is_discussed',
        ]));
        $item->save();
        $item->load(['task.project:id,code', 'presenter:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, $item, __('Agenda item updated.'));
    }

    /**
     * Move the agenda item to a new position in the order.
     */
    public function move(MoveMeetingAgendaItemRequest $request, Team $current_team, Meeting $meeting, MeetingAgendaItem $item): JsonResponse
    {
        $this->authorizeItemOnMeeting($current_team, $meeting, $item);
        Gate::authorize('update', $meeting);

        $item->position = (int) $request->validated('position');
        $item->save();

        return response()->json([
            'agendaItems' => $meeting->agendaItems()
                ->with(['task.project:id,code', 'presenter:id,name'])
                ->get()
                ->map(fn (MeetingAgendaItem $agendaItem): array => $agendaItem->toListArray()),
            'message' => __('Agenda reordered.'),
        ]);
    }

    /**
     * Mark the agenda item discussed or not.
     */
    public function toggle(Request $request, Team $current_team, Meeting $meeting, MeetingAgendaItem $item): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnMeeting($current_team, $meeting, $item);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $item->is_discussed = $request->boolean('is_discussed');
        $item->save();
        $item->load(['task.project:id,code', 'presenter:id,name']);

        return $this->savedResponse($request, $current_team, $meeting, $item, __('Agenda item updated.'));
    }

    /**
     * Remove the agenda item.
     */
    public function destroy(Request $request, Team $current_team, Meeting $meeting, MeetingAgendaItem $item): JsonResponse|RedirectResponse
    {
        $this->authorizeItemOnMeeting($current_team, $meeting, $item);
        Gate::authorize('update', $meeting);

        abort_unless($request->user() !== null, 403);

        $item->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Agenda item removed.')]);

            return to_route('meetings.show', ['current_team' => $current_team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'message' => __('Agenda item removed.'),
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
     * An agenda item scoped by the URL's meeting and team, or a 404 (NFR-1).
     */
    private function authorizeItemOnMeeting(Team $current_team, Meeting $meeting, MeetingAgendaItem $item): void
    {
        $this->authorizeMeetingOnTeam($current_team, $meeting);

        abort_unless($item->meeting_id === $meeting->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Meeting $meeting, MeetingAgendaItem $item, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('meetings.show', ['current_team' => $team->slug, 'meeting' => $meeting]);
        }

        return response()->json([
            'agendaItem' => $item->toListArray(),
            'message' => $message,
        ], $status);
    }
}

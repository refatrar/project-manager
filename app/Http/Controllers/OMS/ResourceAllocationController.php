<?php

namespace App\Http\Controllers\OMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveResourceAllocationRequest;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ResourceAllocationController extends Controller
{
    /**
     * Book a project member's forward hours (RD.md FR-8.3). Managed the
     * same way as milestones and sprints — reuses `ProjectPolicy::update`
     * rather than a dedicated policy, since booking a member's time is
     * project-management work, not a role of its own.
     */
    public function store(SaveResourceAllocationRequest $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $allocation = new ResourceAllocation($request->safe()->only([
            'user_id', 'task_id', 'sprint_id', 'status', 'starts_on', 'ends_on',
            'hours_per_day', 'allocation_percentage', 'notes',
        ]));
        $allocation->project_id = $project->id;
        $allocation->created_by = $user->id;
        $allocation->save();
        $allocation->load(['user:id,name', 'task:id,number,title']);

        return $this->savedResponse($request, $current_team, $project, $allocation, __('Booking created.'), 201);
    }

    /**
     * Update the specified booking.
     */
    public function update(SaveResourceAllocationRequest $request, Team $current_team, Project $project, ResourceAllocation $allocation): JsonResponse|RedirectResponse
    {
        $this->authorizeAllocationOnProject($current_team, $project, $allocation);
        Gate::authorize('update', $project);

        $allocation->fill($request->safe()->only([
            'user_id', 'task_id', 'sprint_id', 'status', 'starts_on', 'ends_on',
            'hours_per_day', 'allocation_percentage', 'notes',
        ]));
        $allocation->save();
        $allocation->load(['user:id,name', 'task:id,number,title']);

        return $this->savedResponse($request, $current_team, $project, $allocation, __('Booking updated.'));
    }

    /**
     * Remove the specified booking. A genuine hard delete — unlike a
     * time-off request, a booking has no `cancelled` status meant to
     * preserve it in place (`AllocationStatus::Cancelled` exists for a
     * booking that fell through while its dates were kept for record;
     * this route is for the true mistake / duplicate-entry case).
     */
    public function destroy(Request $request, Team $current_team, Project $project, ResourceAllocation $allocation): JsonResponse|RedirectResponse
    {
        $this->authorizeAllocationOnProject($current_team, $project, $allocation);
        Gate::authorize('update', $project);

        $allocation->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking deleted.')]);

            return to_route('projects.show', ['current_team' => $current_team->slug, 'project' => $project]);
        }

        return response()->json([
            'message' => __('Booking deleted.'),
        ]);
    }

    /**
     * A project scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeProjectOnTeam(Team $current_team, Project $project): void
    {
        abort_unless($project->team_id === $current_team->id, 404);
    }

    /**
     * A booking scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeAllocationOnProject(Team $current_team, Project $project, ResourceAllocation $allocation): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($allocation->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, ResourceAllocation $allocation, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'allocation' => $allocation->toListArray(),
            'message' => $message,
        ], $status);
    }
}

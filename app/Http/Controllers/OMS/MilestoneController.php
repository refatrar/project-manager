<?php

namespace App\Http\Controllers\OMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveMilestoneRequest;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MilestoneController extends Controller
{
    /**
     * Store a newly created milestone on the project.
     */
    public function store(SaveMilestoneRequest $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $milestone = new Milestone($request->safe()->only([
            'name', 'description', 'status', 'due_on', 'is_billable', 'payment_amount',
        ]));
        $milestone->project_id = $project->id;
        $milestone->position = $project->milestones()->max('position') + 1;
        $milestone->created_by = $user->id;
        $milestone->save();

        return $this->savedResponse($request, $current_team, $project, $milestone, __('Milestone created.'), 201);
    }

    /**
     * Update the specified milestone.
     */
    public function update(SaveMilestoneRequest $request, Team $current_team, Project $project, Milestone $milestone): JsonResponse|RedirectResponse
    {
        $this->authorizeMilestoneOnProject($current_team, $project, $milestone);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $milestone->fill($request->safe()->only([
            'name', 'description', 'status', 'due_on', 'is_billable', 'payment_amount',
        ]));
        $milestone->updated_by = $user->id;
        $milestone->save();

        return $this->savedResponse($request, $current_team, $project, $milestone, __('Milestone updated.'));
    }

    /**
     * Soft delete the specified milestone.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Milestone $milestone): JsonResponse|RedirectResponse
    {
        $this->authorizeMilestoneOnProject($current_team, $project, $milestone);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $milestone->deleted_by = $user->id;
        $milestone->save();
        $milestone->delete();

        return response()->json([
            'message' => __('Milestone deleted.'),
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
     * A milestone scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeMilestoneOnProject(Team $current_team, Project $project, Milestone $milestone): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($milestone->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, Milestone $milestone, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'milestone' => $milestone->toListArray(),
            'message' => $message,
        ], $status);
    }
}

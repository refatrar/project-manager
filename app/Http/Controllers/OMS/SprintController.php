<?php

namespace App\Http\Controllers\OMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveSprintRequest;
use App\Models\OMS\Project;
use App\Models\OMS\Sprint;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SprintController extends Controller
{
    /**
     * Store a newly created sprint on the project.
     */
    public function store(SaveSprintRequest $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $sprint = new Sprint($request->safe()->only([
            'name', 'goal', 'status', 'starts_on', 'ends_on', 'capacity_hours', 'committed_hours',
        ]));
        $sprint->project_id = $project->id;
        $sprint->created_by = $user->id;
        $sprint->save();

        return $this->savedResponse($request, $current_team, $project, $sprint, __('Sprint created.'), 201);
    }

    /**
     * Update the specified sprint.
     */
    public function update(SaveSprintRequest $request, Team $current_team, Project $project, Sprint $sprint): JsonResponse|RedirectResponse
    {
        $this->authorizeSprintOnProject($current_team, $project, $sprint);
        Gate::authorize('update', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $sprint->fill($request->safe()->only([
            'name', 'goal', 'status', 'starts_on', 'ends_on', 'capacity_hours', 'committed_hours',
        ]));
        $sprint->updated_by = $user->id;
        $sprint->save();

        return $this->savedResponse($request, $current_team, $project, $sprint, __('Sprint updated.'));
    }

    /**
     * Soft delete the specified sprint.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Sprint $sprint): JsonResponse|RedirectResponse
    {
        $this->authorizeSprintOnProject($current_team, $project, $sprint);
        Gate::authorize('update', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $sprint->deleted_by = $user->id;
        $sprint->save();
        $sprint->delete();

        return response()->json([
            'message' => __('Sprint deleted.'),
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
     * A sprint scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeSprintOnProject(Team $current_team, Project $project, Sprint $sprint): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($sprint->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, Sprint $sprint, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'sprint' => $sprint->toListArray(),
            'message' => $message,
        ], $status);
    }
}

<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\AddProjectMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveProjectMemberRequest;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectMemberController extends Controller
{
    /**
     * Add a member to the project, restoring a previously removed
     * membership rather than inserting a duplicate (DESIGN.md 3.4).
     */
    public function store(SaveProjectMemberRequest $request, Team $current_team, Project $project, AddProjectMember $addProjectMember): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $member = $addProjectMember->handle(
            $project,
            (int) $request->validated('user_id'),
            $request->safe()->only(['role', 'status', 'allocation_percentage', 'hourly_rate', 'joined_on']),
            $user->id,
        );

        $member->load('user:id,name,email');

        return $this->savedResponse($request, $current_team, $project, $member, __('Member added.'), 201);
    }

    /**
     * Update the specified member's role and allocation.
     */
    public function update(SaveProjectMemberRequest $request, Team $current_team, Project $project, ProjectMember $member): JsonResponse|RedirectResponse
    {
        $this->authorizeMemberOnProject($current_team, $project, $member);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $member->fill($request->safe()->only(['role', 'status', 'allocation_percentage', 'hourly_rate', 'joined_on']));
        $member->updated_by = $user->id;
        $member->save();
        $member->load('user:id,name,email');

        return $this->savedResponse($request, $current_team, $project, $member, __('Member updated.'));
    }

    /**
     * Remove the specified member from the project. The membership is soft
     * deleted, not the user, so re-adding restores this same row.
     */
    public function destroy(Request $request, Team $current_team, Project $project, ProjectMember $member): JsonResponse|RedirectResponse
    {
        $this->authorizeMemberOnProject($current_team, $project, $member);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $member->left_on = Carbon::now();
        $member->deleted_by = $user->id;
        $member->save();
        $member->delete();

        return response()->json([
            'message' => __('Member removed.'),
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
     * A member scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeMemberOnProject(Team $current_team, Project $project, ProjectMember $member): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($member->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, ProjectMember $member, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'member' => $member->toListArray(),
            'message' => $message,
        ], $status);
    }
}

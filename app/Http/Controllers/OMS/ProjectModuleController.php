<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\ReorderProjectModules;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\ReorderProjectModulesRequest;
use App\Http\Requests\OMS\SaveProjectModuleRequest;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use RuntimeException;

class ProjectModuleController extends Controller
{
    /**
     * Store a newly created module on the project.
     */
    public function store(SaveProjectModuleRequest $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $module = new ProjectModule($request->safe()->only([
            'parent_id', 'name', 'description', 'status', 'priority',
            'start_date', 'end_date', 'estimated_hours',
        ]));
        $module->project_id = $project->id;
        $module->position = $project->modules()->where('parent_id', $module->parent_id)->max('position') + 1;
        $module->created_by = $user->id;
        $module->save();

        return $this->savedResponse($request, $current_team, $project, $module, __('Module created.'), 201);
    }

    /**
     * Update the specified module.
     */
    public function update(SaveProjectModuleRequest $request, Team $current_team, Project $project, ProjectModule $module, ReorderProjectModules $cycles): JsonResponse|RedirectResponse
    {
        $this->authorizeModuleOnProject($current_team, $project, $module);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $newParentId = $request->validated('parent_id');

        if ($newParentId !== null && $cycles->createsCycle($project, $module->id, (int) $newParentId)) {
            abort(422, __('That module cannot be moved under its own descendant.'));
        }

        $module->fill($request->safe()->only([
            'parent_id', 'name', 'description', 'status', 'priority',
            'start_date', 'end_date', 'estimated_hours',
        ]));
        $module->updated_by = $user->id;
        $module->save();

        return $this->savedResponse($request, $current_team, $project, $module, __('Module updated.'));
    }

    /**
     * Soft delete the specified module. Its row (and the parent_id / project_module_id
     * values children still point at) is preserved, since this is a soft delete.
     */
    public function destroy(Request $request, Team $current_team, Project $project, ProjectModule $module): JsonResponse|RedirectResponse
    {
        $this->authorizeModuleOnProject($current_team, $project, $module);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $module->deleted_by = $user->id;
        $module->save();
        $module->delete();

        return response()->json([
            'message' => __('Module deleted.'),
        ]);
    }

    /**
     * Apply a batch of position and parent changes from the module tree.
     */
    public function reorder(ReorderProjectModulesRequest $request, Team $current_team, Project $project, ReorderProjectModules $reorder): JsonResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $reorder->handle($project, $request->validated('modules'), $user->id);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'modules' => $project->modules()->orderBy('position')->get()->map(fn (ProjectModule $module): array => $module->toListArray()),
            'message' => __('Modules reordered.'),
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
     * A module scoped by the URL's project and team, or a 404 (NFR-1).
     */
    private function authorizeModuleOnProject(Team $current_team, Project $project, ProjectModule $module): void
    {
        $this->authorizeProjectOnTeam($current_team, $project);

        abort_unless($module->project_id === $project->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, ProjectModule $module, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'module' => $module->toListArray(),
            'message' => $message,
        ], $status);
    }
}

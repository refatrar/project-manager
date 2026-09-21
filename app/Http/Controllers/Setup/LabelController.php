<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SaveLabelRequest;
use App\Models\Setup\Label;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    /**
     * Display a listing of the team's labels.
     */
    public function index(Team $current_team): Response
    {
        $labels = Label::query()
            ->where('team_id', $current_team->id)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->through(fn (Label $label): array => $label->toSetupArray());

        return Inertia::render('setup/labels/index', [
            'labels' => $labels,
        ]);
    }

    /**
     * Store a newly created label.
     */
    public function store(SaveLabelRequest $request, Team $current_team): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $label = new Label($request->safe()->only(['name', 'color', 'description']));
        $label->team_id = $current_team->id;
        $label->created_by = $user->id;
        $label->save();

        return $this->savedResponse($request, $current_team, $label, __('Label created.'), 201);
    }

    /**
     * Update the specified label.
     */
    public function update(SaveLabelRequest $request, Team $current_team, Label $label): JsonResponse|RedirectResponse
    {
        $this->authorizeLabelOnTeam($current_team, $label);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $label->fill($request->safe()->only(['name', 'color', 'description']));
        $label->updated_by = $user->id;
        $label->save();

        return $this->savedResponse($request, $current_team, $label, __('Label updated.'));
    }

    /**
     * Delete the specified label. Labels have no soft-delete column — this
     * is a genuine hard delete, and `label_task` cascades with it.
     */
    public function destroy(Request $request, Team $current_team, Label $label): JsonResponse|RedirectResponse
    {
        $this->authorizeLabelOnTeam($current_team, $label);
        abort_unless($request->user() !== null, 403);

        $label->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Label deleted.')]);

            return to_route('setup.labels.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('Label deleted.'),
        ]);
    }

    /**
     * A label scoped by the URL's current team, or a 404 (NFR-1).
     */
    private function authorizeLabelOnTeam(Team $current_team, Label $label): void
    {
        abort_unless($label->team_id === $current_team->id, 404);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Label $label, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('setup.labels.index', ['current_team' => $team->slug]);
        }

        return response()->json([
            'label' => $label->toSetupArray(),
            'message' => $message,
        ], $status);
    }
}

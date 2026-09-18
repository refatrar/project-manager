<?php

namespace App\Http\Controllers\Setup;

use App\Enums\ScopeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SaveScopeRequest;
use App\Models\Setup\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScopeController extends Controller
{
    /**
     * Display a listing of the scopes.
     */
    public function index(): Response
    {
        $scopes = Scope::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->through(fn (Scope $scope): array => $scope->toSetupArray());

        return Inertia::render('setup/scopes/index', [
            'scopes' => $scopes,
            'statusOptions' => ScopeStatus::options(),
        ]);
    }

    /**
     * Store a newly created scope.
     */
    public function store(SaveScopeRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $scope = new Scope($request->safe()->only(['name', 'description', 'status']));
        $scope->created_by = $user->id;
        $scope->save();

        return $this->savedResponse($request, $scope, __('Scope created.'), 201);
    }

    /**
     * Update the specified scope.
     */
    public function update(SaveScopeRequest $request, string $current_team, Scope $scope): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $scope->fill($request->safe()->only(['name', 'description', 'status']));
        $scope->updated_by = $user->id;
        $scope->save();

        return $this->savedResponse($request, $scope, __('Scope updated.'));
    }

    /**
     * Soft delete the specified scope.
     */
    public function destroy(Request $request, string $current_team, Scope $scope): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $scope->deleted_by = $user->id;
        $scope->save();
        $scope->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Scope deleted.')]);

            return to_route('setup.scopes.index');
        }

        return response()->json([
            'message' => __('Scope deleted.'),
        ]);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Scope $scope, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('setup.scopes.index');
        }

        return response()->json([
            'scope' => $scope->toSetupArray(),
            'message' => $message,
        ], $status);
    }
}

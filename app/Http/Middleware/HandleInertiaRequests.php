<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // `$request->user()` is guard-agnostic and, since `Authenticate`
        // switches the default guard to whichever one it just checked
        // (`Auth::shouldUse($guard)`), can resolve to an `App\Models\Admin`
        // on an `/admin/*` request — which has no team relationships at
        // all. Only a real `User` has teams to share here.
        $teamUser = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $teamUser?->currentTeam ? $teamUser->toUserTeam($teamUser->currentTeam) : null,
            'teams' => fn () => $teamUser?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}

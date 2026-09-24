<?php

namespace App\Http\Middleware;

use App\Enums\TeamModulePermission;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('web');
        $team = $this->team($request);
        $ability = TeamModulePermission::tryFrom($permission);

        abort_unless(
            $user !== null && $team instanceof Team && $ability !== null && $user->teamCan($team, $ability),
            403,
        );

        return $next($request);
    }

    /**
     * Get the team associated with the request. Not always already resolved
     * to a model by route-model-binding — that only fires when the matched
     * controller action also type-hints `Team $current_team`, which not
     * every team-scoped controller does (@see EnsureTeamMembership::team()).
     */
    private function team(Request $request): ?Team
    {
        $team = $request->route('current_team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team instanceof Team ? $team : null;
    }
}

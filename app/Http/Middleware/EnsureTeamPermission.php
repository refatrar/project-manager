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
        $team = $request->route('current_team');
        $ability = TeamModulePermission::tryFrom($permission);

        abort_unless(
            $user !== null && $team instanceof Team && $ability !== null && $user->teamCan($team, $ability),
            403,
        );

        return $next($request);
    }
}

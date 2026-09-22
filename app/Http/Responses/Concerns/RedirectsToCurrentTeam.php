<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        // Phase 7: registration no longer creates a team, so a freshly
        // signed-up (or freshly verified) user genuinely has none yet —
        // `$redirect` (a team-prefixed path fragment) has nowhere to
        // point, so send them to the holding page instead of a team URL.
        if ($team === null) {
            return '/start';
        }

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): ?Team
    {
        $user = $request->user('web');

        abort_if(! $user, 403);

        return $user->currentTeam ?? $user->personalTeam();
    }
}

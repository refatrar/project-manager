<?php

namespace App\Http\Controllers;

use App\Actions\Teams\FindPendingInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The post-login/register landing point (Phase 7): registration no longer
 * creates a team, so this is where a teamless user's `redirectTo('login')`
 * path actually goes. Redirects straight through to the team dashboard for
 * anyone who already has a team — this route only ever renders a page for
 * the genuinely teamless case.
 */
class StartController
{
    public function __invoke(Request $request, FindPendingInvitations $findPendingInvitations): Response|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        if ($team !== null) {
            return to_route('dashboard', ['current_team' => $team->slug]);
        }

        return Inertia::render('no-team', [
            'pendingInvitations' => $findPendingInvitations->handle($user),
        ]);
    }
}

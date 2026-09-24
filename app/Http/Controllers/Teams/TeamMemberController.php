<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user, TeamAccessControl $access): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $actor = $request->user('web');
        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Role changes only reach people "below" the actor: never their own
        // role, never the team lead (that goes through the admin panel's
        // `AssignTeamLeader`), and never someone whose current role holds
        // permissions the actor doesn't — or they could strip a peer or
        // superior. The target role itself is limited by the request's
        // `grantableSlugs` rule.
        abort_if($actor === null || $actor->is($user), 403, __('You cannot change your own role.'));
        abort_if($membership->roleSlug() === TeamRole::TeamLead->value, 403, __('The team lead\'s role is changed from the admin panel.'));
        abort_unless(
            in_array($membership->roleSlug(), $access->grantableSlugs($actor, $team), true),
            403,
            __('You cannot change the role of someone with permissions you do not have.'),
        );

        $membership->update(['role' => $request->validated('role')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Request $request, Team $team, User $user, TeamAccessControl $access): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        $membership = $team->memberships()->where('user_id', $user->id)->first();

        // Checks the target's own membership, not `$team->owner()` (which
        // only ever finds one team_lead row).
        abort_if($membership?->roleSlug() === TeamRole::TeamLead->value, 403, __('The team lead cannot be removed.'));

        $actor = $request->user('web');
        abort_if($actor === null || $actor->is($user), 403, __('Use "Leave team" to leave a team.'));
        abort_if(
            $membership !== null && ! in_array($membership->roleSlug(), $access->grantableSlugs($actor, $team), true),
            403,
            __('You cannot remove someone with permissions you do not have.'),
        );

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}

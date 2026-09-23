<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Clears a team's leader without removing them from the team (Phase 7.9).
 * A team may sit with members and no owner — the same gap `CreateTeam`
 * already allows between creation and the first leader assignment.
 */
class RemoveTeamLeader
{
    /**
     * Demote every current owner to `TeamRole::Member`. Membership stays,
     * and `current_team_id` stays, because they are still on the team.
     * What they lose is the owner-wide project visibility that role grants.
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $team->memberships()
                ->where('role', TeamRole::Owner->value)
                ->update(['role' => TeamRole::Member->value]);
        });
    }
}

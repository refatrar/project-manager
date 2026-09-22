<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants `TeamRole::Owner` on a team to an existing user (Phase 7: a super
 * admin assigns a "team leader" — the same role self-service team creation
 * already grants its creator, just admin-assigned instead of automatic).
 */
class AssignTeamLeader
{
    /**
     * Reassigning a leader when one already exists demotes the previous
     * leader to `TeamRole::Member` rather than refusing the action — a
     * super admin fixing a mis-assignment is a real, expected case, not an
     * error condition.
     */
    public function handle(Team $team, User $user): void
    {
        DB::transaction(function () use ($team, $user) {
            $team->memberships()
                ->where('role', TeamRole::Owner->value)
                ->where('user_id', '!=', $user->id)
                ->update(['role' => TeamRole::Member->value]);

            $team->memberships()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => TeamRole::Owner->value],
            );

            // A freshly-registered user has no current team (Phase 7: no
            // auto-created personal team) — becoming a leader is exactly
            // the moment they should land on this team's dashboard next.
            // Someone who already has a team is left on it; being made a
            // leader elsewhere doesn't imply they want to switch away.
            if ($user->current_team_id === null) {
                $user->switchTeam($team);
            }
        });
    }
}

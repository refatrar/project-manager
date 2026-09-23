<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Database\Seeders\LabelSeeder;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team. Default labels are seeded here rather than
     * globally — `labels.team_id` means each team needs its own set, not
     * one shared table (unlike `scopes`/`task_types`) — and that's true
     * whether or not the team has an owner yet.
     *
     * `$user` is optional (Phase 7): a super admin creates a team with no
     * members at all, and assigns a team leader as a separate step
     * (`AssignTeamLeader`) — self-service creation (still `$user` given)
     * keeps working exactly as before, immediately owning what it made.
     */
    public function handle(?User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            if ($user !== null) {
                $team->memberships()->create([
                    'user_id' => $user->id,
                    'role' => TeamRole::Owner,
                ]);

                $user->switchTeam($team);
            }

            (new LabelSeeder)->run($team);

            app(TeamAccessControl::class)->ensureCatalogue();

            return $team;
        });
    }
}

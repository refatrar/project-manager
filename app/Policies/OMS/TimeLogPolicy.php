<?php

namespace App\Policies\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamRole;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;

class TimeLogPolicy
{
    public function __construct(
        private readonly ProjectPolicy $projectPolicy,
    ) {
        //
    }

    /**
     * Determine whether the user can view their time logs on the team.
     * Personal, like time-off requests — there is
     * no team-admin override here; timesheet approval (a separate,
     * not-yet-built surface) is where a manager acts on someone else's
     * logs, not this policy.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the entry — their own only.
     */
    public function view(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id;
    }

    /**
     * Determine whether the user can log time on the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can edit the entry — only their own,
     * and only while it hasn't been decided yet. Once approved or
     * rejected, it's a fixed record.
     */
    public function update(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id && $timeLog->approval_status === ApprovalStatus::Pending;
    }

    /**
     * Determine whether the user can delete the entry. Same boundary as `update`.
     */
    public function delete(User $user, TimeLog $timeLog): bool
    {
        return $this->update($user, $timeLog);
    }

    /**
     * Determine whether the user can stop this specific running timer.
     */
    public function stop(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id && $timeLog->ended_at === null;
    }

    /**
     * Determine whether the user can approve or reject the entry — only
     * once it's been submitted, and only the project manager it was
     * logged against (RD.md names "project manager" as who "approves
     * timesheets"), reusing `ProjectPolicy::update`'s own manage-rights
     * check rather than re-deriving it. An entry logged against no
     * project at all (`project_id` null — time with no project context,
     * e.g. general admin work) has no project manager to fall back on,
     * so a team admin decides those instead.
     */
    public function decide(User $user, TimeLog $timeLog): bool
    {
        if ($timeLog->approval_status !== ApprovalStatus::Submitted) {
            return false;
        }

        if ($timeLog->project_id === null) {
            $timeLog->loadMissing('team');
            $role = $user->teamRole($timeLog->team);

            return $role !== null && $role->isAtLeast(TeamRole::Admin);
        }

        $timeLog->loadMissing('project');

        return $this->projectPolicy->update($user, $timeLog->project);
    }
}

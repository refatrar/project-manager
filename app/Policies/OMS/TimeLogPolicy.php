<?php

namespace App\Policies\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamModulePermission;
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
        return $user->teamCan($team, TeamModulePermission::ManageTimeLogs);
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
        return $user->teamCan($team, TeamModulePermission::ManageTimeLogs);
    }

    /**
     * Determine whether the user can edit the entry — only their own,
     * and only while it hasn't been decided yet. Once approved or
     * rejected, it's a fixed record.
     */
    public function update(User $user, TimeLog $timeLog): bool
    {
        return $timeLog->user_id === $user->id
            && $timeLog->approval_status === ApprovalStatus::Pending
            && $this->canLogTime($user, $timeLog);
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
        return $timeLog->user_id === $user->id
            && $timeLog->ended_at === null
            && $this->canLogTime($user, $timeLog);
    }

    /**
     * Determine whether the user can approve or reject the entry — only
     * once it's been submitted. A `timesheet-approvals.decide` holder
     * decides any entry on the team; otherwise only the project manager
     * it was logged against (RD.md names "project manager" as who
     * "approves timesheets"), reusing `ProjectPolicy::update`'s own
     * manage-rights check rather than re-deriving it. An entry logged
     * against no project (`project_id` null) has no project manager to
     * fall back on. Nobody decides their own entry, whatever their
     * standing.
     */
    public function decide(User $user, TimeLog $timeLog): bool
    {
        if ($timeLog->approval_status !== ApprovalStatus::Submitted || $timeLog->user_id === $user->id) {
            return false;
        }

        $timeLog->loadMissing('team');

        // `timesheet-approvals.decide` is the team-wide approver grant:
        // every entry on the team, with or without a project.
        if ($timeLog->team !== null && $user->teamCan($timeLog->team, TeamModulePermission::DecideTimesheets)) {
            return true;
        }

        if ($timeLog->project_id === null) {
            return false;
        }

        $timeLog->loadMissing('project');

        return $this->projectPolicy->update($user, $timeLog->project);
    }

    /**
     * Owning an entry isn't enough on its own: a role that loses
     * `time-logs.manage` stops being able to change its logs too.
     */
    private function canLogTime(User $user, TimeLog $timeLog): bool
    {
        $timeLog->loadMissing('team');

        return $timeLog->team !== null && $user->teamCan($timeLog->team, TeamModulePermission::ManageTimeLogs);
    }
}

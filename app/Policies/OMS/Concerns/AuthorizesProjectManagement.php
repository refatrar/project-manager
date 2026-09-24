<?php

namespace App\Policies\OMS\Concerns;

use App\Enums\TeamModulePermission;
use App\Models\OMS\Project;
use App\Models\User;

/**
 * The "who can manage this project" check, shared by `ProjectPolicy`,
 * `TaskPolicy`, and `MeetingPolicy` — each reaches the project differently
 * (a route parameter vs. a relation), so the call site stays per-policy,
 * but the condition itself is one rule everywhere: additive, on top of
 * whatever the policy already grants (e.g. `ProjectPolicy`'s own
 * view-implies-manage `hasWideVisibility`, kept as-is where it already
 * exists).
 */
trait AuthorizesProjectManagement
{
    /**
     * Determine whether the user has project-lead-level authority over the
     * project: they're its designated Project Lead, or their team role
     * grants team-wide project management, or their project membership
     * role can manage project work.
     */
    protected function managesProject(User $user, Project $project): bool
    {
        return $this->hasTeamWideProjectAuthority($user, $project)
            || $this->isProjectLead($user, $project)
            || $this->hasManagingMembership($user, $project);
    }

    /**
     * Determine whether the user's team role grants management authority
     * over every project on the team (Team Lead, by default).
     */
    protected function hasTeamWideProjectAuthority(User $user, Project $project): bool
    {
        return $user->teamCan($project->team, TeamModulePermission::ManageAllProjects);
    }

    /**
     * Determine whether the user is this project's designated Project Lead.
     * Also requires current team membership: someone removed from the team
     * does not keep Project Lead authority just because the `projects` row
     * hasn't been reassigned yet.
     */
    protected function isProjectLead(User $user, Project $project): bool
    {
        return $project->project_lead_id !== null
            && $project->project_lead_id === $user->id
            && $user->belongsToTeam($project->team);
    }

    /**
     * Determine whether the user holds an active project membership whose
     * role can manage project work (Owner/Manager/Lead).
     */
    protected function hasManagingMembership(User $user, Project $project): bool
    {
        $membership = $project->members()->active()->where('user_id', $user->id)->first();

        return $membership !== null && $membership->role->canManageProject();
    }
}

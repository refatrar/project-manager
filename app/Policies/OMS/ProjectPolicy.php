<?php

namespace App\Policies\OMS;

use App\Enums\TeamModulePermission;
use App\Models\OMS\Project;
use App\Models\Team;
use App\Models\User;
use App\Policies\OMS\Concerns\AuthorizesProjectManagement;

class ProjectPolicy
{
    use AuthorizesProjectManagement;

    /**
     * Determine whether the user can view the team's project list.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ViewProjects);
    }

    /**
     * Determine whether the user can view the project. Includes the
     * Project Lead / Team Lead even without a project membership row —
     * they can't manage what they can't see.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->hasWideVisibility($user, $project)
            || $this->isActiveMember($user, $project)
            || $this->managesProject($user, $project);
    }

    /**
     * Determine whether the user can create a project on the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::CreateProjects);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user can archive the project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user manages the project: through wide
     * team-level visibility (existing behavior — view-all also implies
     * manage here, per ADR-011), or through Project Lead / Team Lead /
     * project-role authority (`AuthorizesProjectManagement`).
     *
     * @see docs/architecture-decisions.md ADR-011
     */
    protected function canManage(User $user, Project $project): bool
    {
        return $this->hasWideVisibility($user, $project) || $this->managesProject($user, $project);
    }

    /**
     * Determine whether the user's team role grants visibility into every
     * project regardless of project membership.
     *
     * @see docs/architecture-decisions.md ADR-011
     */
    protected function hasWideVisibility(User $user, Project $project): bool
    {
        return $user->teamCan($project->team, TeamModulePermission::ViewAllProjects);
    }

    /**
     * Determine whether the user is an active member of the project.
     */
    protected function isActiveMember(User $user, Project $project): bool
    {
        return $project->members()->active()->where('user_id', $user->id)->exists();
    }
}

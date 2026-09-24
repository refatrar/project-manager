<?php

namespace App\Policies\OMS;

use App\Enums\TeamModulePermission;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\User;
use App\Policies\OMS\Concerns\AuthorizesProjectManagement;

class TaskPolicy
{
    use AuthorizesProjectManagement;

    /**
     * Determine whether the user can view the project's tasks.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $this->canUseProjects($user, $project) && (
            $this->hasWideVisibility($user, $project)
            || $this->isActiveMember($user, $project)
            || $this->managesProject($user, $project)
        );
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user, $task->project);
    }

    /**
     * Determine whether the user can create a task on the project. Broader
     * than "manage" — any active project member can log work, and (unlike
     * `update`/`delete`) the Project Lead / Team Lead can too even without
     * a project membership row of their own.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project);
    }

    /**
     * Determine whether the user can edit the task's own fields (title,
     * description, type, priority, dates, grouping). Requires project
     * management rights, unlike moving the task on the board.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->canManage($user, $task->project);
    }

    /**
     * Determine whether the user can move the task to a new status or
     * position. Project managers can move any task; an assignee can move
     * their own.
     */
    public function changeStatus(User $user, Task $task): bool
    {
        return $this->canManage($user, $task->project)
            || ($this->canUseProjects($user, $task->project) && $this->isAssignee($user, $task));
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->canManage($user, $task->project);
    }

    /**
     * @see App\Policies\OMS\ProjectPolicy::canManage() — same rule, reached
     * via `AuthorizesProjectManagement` since Task's project is a relation,
     * not a route parameter.
     */
    private function canManage(User $user, Project $project): bool
    {
        return $this->canUseProjects($user, $project)
            && ($this->hasWideVisibility($user, $project) || $this->managesProject($user, $project));
    }

    private function hasWideVisibility(User $user, Project $project): bool
    {
        return $user->teamCan($project->team, TeamModulePermission::ViewAllProjects);
    }

    private function isActiveMember(User $user, Project $project): bool
    {
        return $project->members()->active()->where('user_id', $user->id)->exists();
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return $task->assignments()->where('user_id', $user->id)->whereNull('unassigned_at')->exists();
    }
}

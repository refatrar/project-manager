<?php

namespace App\Policies\OMS;

use App\Enums\TeamModulePermission;
use App\Enums\TodoListType;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;

class TodoListPolicy
{
    public function __construct(
        private readonly MeetingPolicy $meetingPolicy,
    ) {
        //
    }

    /**
     * Determine whether the user can view their to-do lists on the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ManageTodos);
    }

    /**
     * Determine whether the user can view the list. Personal to-do lists
     * are private — there is no team-admin override, unlike projects. A
     * task checklist is shared project work, not personal, so any active
     * project member can see and manage it, same as creating a task. A
     * meeting's action list is likewise shared, collaborative work: whoever
     * can see the meeting (any attendee, or the same wide/project
     * visibility `MeetingPolicy::view` already grants) can see and manage
     * its action items too — capturing and ticking off action items is not
     * restricted to whoever organizes or manages the meeting.
     */
    public function view(User $user, TodoList $list): bool
    {
        if ($list->owner_id === $user->id) {
            return $this->isShared($list) || $this->canManageTodos($user, $list);
        }

        if ($list->type === TodoListType::TaskChecklist) {
            return $this->canViewChecklistTask($user, $list);
        }

        if ($list->type === TodoListType::MeetingActions) {
            return $this->canViewMeeting($user, $list);
        }

        return false;
    }

    /**
     * Determine whether the user can create a to-do list on the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ManageTodos);
    }

    /**
     * Determine whether the user can update the list — including adding,
     * toggling and removing its items, which all authorize against this.
     */
    public function update(User $user, TodoList $list): bool
    {
        return $this->view($user, $list);
    }

    /**
     * Determine whether the user can delete the list. Deliberately not
     * extended to task checklists — those are deleted only by deleting
     * the task they belong to (`task_id` cascades).
     */
    public function delete(User $user, TodoList $list): bool
    {
        return $list->owner_id === $user->id
            && ($this->isShared($list) || $this->canManageTodos($user, $list));
    }

    /**
     * Task checklists and meeting action lists are shared project work,
     * governed by project/meeting access rather than `todos.manage`.
     */
    private function isShared(TodoList $list): bool
    {
        return $list->type === TodoListType::TaskChecklist || $list->type === TodoListType::MeetingActions;
    }

    /**
     * A personal list is only usable while the owner's team role still
     * grants `todos.manage` — revoking it has to reach existing lists too.
     */
    private function canManageTodos(User $user, TodoList $list): bool
    {
        $list->loadMissing('team');

        return $list->team !== null && $user->teamCan($list->team, TeamModulePermission::ManageTodos);
    }

    /**
     * Determine whether the user can see the checklist's task, reusing
     * `TaskPolicy::view` (which requires `projects.view`, then project
     * membership, Project Lead, or view-all/manage-all standing) so the
     * checklist is open to exactly the people who can open the task.
     */
    private function canViewChecklistTask(User $user, TodoList $list): bool
    {
        $list->loadMissing('task.project');

        return $list->task !== null && $user->can('view', $list->task);
    }

    /**
     * Determine whether the user can view the meeting the action list
     * belongs to, reusing `MeetingPolicy::view` rather than re-deriving
     * meeting visibility here.
     */
    private function canViewMeeting(User $user, TodoList $list): bool
    {
        $list->loadMissing('meeting');

        return $list->meeting !== null && $this->meetingPolicy->view($user, $list->meeting);
    }
}

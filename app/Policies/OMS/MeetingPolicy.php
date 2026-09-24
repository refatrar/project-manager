<?php

namespace App\Policies\OMS;

use App\Enums\MeetingAttendeeRole;
use App\Enums\TeamModulePermission;
use App\Models\OMS\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Policies\OMS\Concerns\AuthorizesProjectManagement;

class MeetingPolicy
{
    use AuthorizesProjectManagement;

    /**
     * Determine whether the user can view the team's meeting list.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ViewMeetings);
    }

    /**
     * Determine whether the user can view the meeting. A team-wide meeting
     * (no project) is visible to the whole team; a project meeting follows
     * the project's own visibility rule (ADR-011).
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if ($meeting->project_id === null) {
            return $user->teamCan($meeting->team, TeamModulePermission::ViewMeetings);
        }

        return $this->hasWideVisibility($user, $meeting)
            || $this->isActiveProjectMember($user, $meeting)
            || $this->managesProject($user, $meeting->project);
    }

    /**
     * Determine whether the user can schedule a meeting on the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::CreateMeetings);
    }

    /**
     * Determine whether the user can update the meeting's own fields.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        return $this->canManage($user, $meeting);
    }

    /**
     * Determine whether the user can cancel the meeting.
     */
    public function cancel(User $user, Meeting $meeting): bool
    {
        return $this->canManage($user, $meeting);
    }

    /**
     * Determine whether the user can delete the meeting.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->canManage($user, $meeting);
    }

    /**
     * Determine whether the user can record or publish the meeting's
     * minutes and decisions. Broader than `canManage` — the attendee
     * invited specifically as the note taker can do this too, even
     * without rights to edit the meeting itself or its attendee list.
     */
    public function recordMinutes(User $user, Meeting $meeting): bool
    {
        if ($this->canManage($user, $meeting)) {
            return true;
        }

        return $meeting->attendees()
            ->where('user_id', $user->id)
            ->where('role', MeetingAttendeeRole::NoteTaker->value)
            ->exists();
    }

    /**
     * Determine whether the user organized the meeting, manages its
     * project (as its Project Lead, its team's Team Lead, or through a
     * managing project role), or has team-admin-level wide visibility
     * into it.
     */
    private function canManage(User $user, Meeting $meeting): bool
    {
        if ($meeting->organized_by === $user->id) {
            return true;
        }

        if ($this->hasWideVisibility($user, $meeting)) {
            return true;
        }

        if ($user->teamCan($meeting->team, TeamModulePermission::ManageAllMeetings)) {
            return true;
        }

        if ($meeting->project_id === null) {
            return false;
        }

        return $this->isProjectLead($user, $meeting->project) || $this->hasManagingMembership($user, $meeting->project);
    }

    private function hasWideVisibility(User $user, Meeting $meeting): bool
    {
        return $user->teamCan($meeting->team, TeamModulePermission::ViewAllMeetings);
    }

    private function isActiveProjectMember(User $user, Meeting $meeting): bool
    {
        return $meeting->project !== null && $meeting->project->members()->active()->where('user_id', $user->id)->exists();
    }
}

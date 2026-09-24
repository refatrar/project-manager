<?php

namespace App\Policies\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamModulePermission;
use App\Models\OMS\TimeOffRequest;
use App\Models\Team;
use App\Models\User;

class TimeOffRequestPolicy
{
    /**
     * Determine whether the user can view time-off requests on the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ViewTimeOff);
    }

    /**
     * Determine whether the user can view the request — their own, or any
     * on the team if they can approve requests there.
     */
    public function view(User $user, TimeOffRequest $request): bool
    {
        return $request->user_id === $user->id || $this->canApprove($user, $request);
    }

    /**
     * Determine whether the user can file a time-off request on the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->teamCan($team, TeamModulePermission::ManageTimeOff);
    }

    /**
     * Determine whether the user can edit the request's own fields — only
     * while it is still pending. Once decided, the record is a fixed
     * decision, not an editable draft.
     */
    public function update(User $user, TimeOffRequest $request): bool
    {
        return $request->user_id === $user->id
            && $request->status === ApprovalStatus::Pending
            && $request->team_id !== null
            && $user->teamCan($request->loadMissing('team')->team, TeamModulePermission::ManageTimeOff);
    }

    /**
     * Determine whether the user can withdraw the request. Same boundary
     * as `update` — only the requester, only while pending.
     */
    public function cancel(User $user, TimeOffRequest $request): bool
    {
        return $this->update($user, $request);
    }

    /**
     * Determine whether the user can approve or reject the request. A
     * decision is final in this slice — a already-decided request cannot
     * be re-decided — and nobody decides their own request.
     */
    public function decide(User $user, TimeOffRequest $request): bool
    {
        return $request->user_id !== $user->id
            && $this->canApprove($user, $request)
            && $request->status === ApprovalStatus::Pending;
    }

    /**
     * Determine whether the user has approver-level (team admin) standing
     * on the team the request was filed under.
     */
    private function canApprove(User $user, TimeOffRequest $request): bool
    {
        if ($request->team_id === null) {
            return false;
        }

        $request->loadMissing('team');

        return $user->teamCan($request->team, TeamModulePermission::DecideTimeOff);
    }
}

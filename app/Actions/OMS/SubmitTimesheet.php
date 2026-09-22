<?php

namespace App\Actions\OMS;

use App\Enums\ApprovalStatus;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubmitTimesheet
{
    /**
     * Move every still-pending, already-finished entry the user logged in
     * the given week into `submitted`, closing it to further self-edits
     * (`TimeLogPolicy::update`/`delete` only allow `pending`) and handing
     * it to a manager to approve or reject — the approval step RD.md's
     * project-manager role description names, not yet built here.
     *
     * A running timer is never included — it has no `ended_at` yet, so
     * there's nothing finished to submit; the user submits the rest of
     * the week and this entry later once it's stopped.
     */
    public function handle(User $user, Team $team, Carbon $weekStart, Carbon $weekEnd): int
    {
        return TimeLog::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->whereNotNull('ended_at')
            ->whereBetween('logged_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->update(['approval_status' => ApprovalStatus::Submitted->value, 'updated_by' => $user->id]);
    }
}

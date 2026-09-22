<?php

namespace App\Actions\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeLogSource;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class StartTimeLog
{
    /**
     * Start a running timer for the user, writing a `time_logs` row with
     * no `ended_at` (RD.md FR-8.6, FR-8.7). A user may only have one
     * running timer at a time across the whole app — this is the same
     * rule `ToggleMeetingTimer` enforces for the meeting-specific timer,
     * kept as a separate, intentionally duplicated implementation rather
     * than a shared refactor, so the already-shipped meeting timer isn't
     * touched by this more general one.
     *
     * @param  array<string, mixed>  $attributes  project_id, task_id, todo_item_id, activity_type, description
     *
     * @throws RuntimeException when the user already has a running timer
     */
    public function handle(User $user, Team $team, array $attributes): TimeLog
    {
        if (TimeLog::query()->running()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('You already have a running timer.');
        }

        $timeLog = new TimeLog($attributes);
        $timeLog->team_id = $team->id;
        $timeLog->user_id = $user->id;
        $timeLog->source = TimeLogSource::Timer;
        $timeLog->started_at = Carbon::now();
        $timeLog->logged_on = Carbon::now();
        // Set explicitly, not left to the column's DB default — the
        // in-memory model would otherwise have no `approval_status`
        // attribute until a fresh reload, and casting null to
        // ApprovalStatus blows up (the same DB-default-vs-PHP-set trap
        // documented in MEMORY.md).
        $timeLog->approval_status = ApprovalStatus::Pending;
        $timeLog->created_by = $user->id;
        $timeLog->save();

        return $timeLog;
    }
}

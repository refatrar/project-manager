<?php

namespace App\Actions\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Models\OMS\Meeting;
use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class ToggleMeetingTimer
{
    /**
     * Start a timer for the user against the meeting, writing a running
     * `time_logs` row (RD.md FR-6.6). A user may only have one running
     * timer at a time, across any meeting or task, so double-clicking
     * "start" on two different meetings can't silently overlap.
     *
     * @throws RuntimeException when the user already has a running timer
     */
    public function start(Meeting $meeting, User $user): TimeLog
    {
        if (TimeLog::query()->running()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('You already have a running timer.');
        }

        $timeLog = new TimeLog([
            'project_id' => $meeting->project_id,
            'meeting_id' => $meeting->id,
            'activity_type' => TimeLogActivityType::Meeting,
            'source' => TimeLogSource::Timer,
            'started_at' => Carbon::now(),
            'logged_on' => Carbon::now()->toDateString(),
        ]);
        $timeLog->team_id = $meeting->team_id;
        $timeLog->user_id = $user->id;
        // Set explicitly rather than left to the column's DB default: the
        // in-memory model would otherwise have no `approval_status`
        // attribute until a fresh reload, and casting null to
        // ApprovalStatus blows up (same trap as `meetings.status`).
        $timeLog->approval_status = ApprovalStatus::Pending;
        $timeLog->created_by = $user->id;
        $timeLog->save();

        return $timeLog;
    }

    /**
     * Stop the user's running timer against the meeting, stamping its
     * duration.
     *
     * @throws RuntimeException when the user has no running timer on this meeting
     */
    public function stop(Meeting $meeting, User $user): TimeLog
    {
        $timeLog = $meeting->timeLogs()->running()->where('user_id', $user->id)->first();

        if ($timeLog === null) {
            throw new RuntimeException('No running timer for this meeting.');
        }

        $endedAt = Carbon::now();
        $timeLog->ended_at = $endedAt;
        $timeLog->duration_minutes = max(1, (int) $timeLog->started_at->diffInMinutes($endedAt));
        $timeLog->updated_by = $user->id;
        $timeLog->save();

        return $timeLog;
    }
}

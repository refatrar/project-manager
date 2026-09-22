<?php

namespace App\Actions\OMS;

use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class StopTimeLog
{
    /**
     * Stop the user's running timer, stamping its duration.
     *
     * @throws RuntimeException when the entry is already stopped
     */
    public function handle(TimeLog $timeLog, User $user): TimeLog
    {
        if ($timeLog->ended_at !== null) {
            throw new RuntimeException('That timer is already stopped.');
        }

        $endedAt = Carbon::now();
        $timeLog->ended_at = $endedAt;
        $timeLog->duration_minutes = max(1, (int) $timeLog->started_at->diffInMinutes($endedAt));
        $timeLog->updated_by = $user->id;
        $timeLog->save();

        return $timeLog;
    }
}

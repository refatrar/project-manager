<?php

namespace App\Actions\OMS;

use App\Models\OMS\Activity;
use App\Models\OMS\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class RecordActivity
{
    /**
     * Append one row to the activity feed. Activities are never updated or
     * deleted — this is the only writer.
     *
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Team $team,
        ?Project $project,
        ?int $userId,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): Activity {
        $activity = new Activity([
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
        ]);
        $activity->team_id = $team->id;
        $activity->project_id = $project?->id;
        $activity->user_id = $userId;

        if ($subject !== null) {
            $activity->subject()->associate($subject);
        }

        $activity->save();

        return $activity;
    }
}

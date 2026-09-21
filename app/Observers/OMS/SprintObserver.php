<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\Sprint;

class SprintObserver
{
    /**
     * Handle the Sprint "created" event.
     */
    public function created(Sprint $sprint): void
    {
        $sprint->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $sprint->project->team,
            project: $sprint->project,
            userId: $sprint->created_by,
            event: 'sprint.created',
            description: "Sprint \"{$sprint->name}\" was added",
            subject: $sprint,
        );
    }

    /**
     * Handle the Sprint "deleted" event.
     */
    public function deleted(Sprint $sprint): void
    {
        $sprint->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $sprint->project->team,
            project: $sprint->project,
            userId: $sprint->deleted_by,
            event: 'sprint.deleted',
            description: "Sprint \"{$sprint->name}\" was deleted",
        );
    }
}

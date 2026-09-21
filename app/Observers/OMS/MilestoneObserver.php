<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\Milestone;

class MilestoneObserver
{
    /**
     * Handle the Milestone "created" event.
     */
    public function created(Milestone $milestone): void
    {
        $milestone->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $milestone->project->team,
            project: $milestone->project,
            userId: $milestone->created_by,
            event: 'milestone.created',
            description: "Milestone \"{$milestone->name}\" was added",
            subject: $milestone,
        );
    }

    /**
     * Handle the Milestone "deleted" event.
     */
    public function deleted(Milestone $milestone): void
    {
        $milestone->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $milestone->project->team,
            project: $milestone->project,
            userId: $milestone->deleted_by,
            event: 'milestone.deleted',
            description: "Milestone \"{$milestone->name}\" was deleted",
        );
    }
}

<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\Project;

class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        app(RecordActivity::class)->handle(
            team: $project->team,
            project: $project,
            userId: $project->created_by,
            event: 'project.created',
            description: "Project \"{$project->name}\" was created",
            subject: $project,
        );
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        app(RecordActivity::class)->handle(
            team: $project->team,
            project: $project,
            userId: $project->deleted_by,
            event: 'project.deleted',
            description: "Project \"{$project->name}\" was deleted",
        );
    }
}

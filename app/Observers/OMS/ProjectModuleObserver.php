<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\ProjectModule;

class ProjectModuleObserver
{
    /**
     * Handle the ProjectModule "created" event.
     */
    public function created(ProjectModule $module): void
    {
        app(RecordActivity::class)->handle(
            team: $module->project->team,
            project: $module->project,
            userId: $module->created_by,
            event: 'module.created',
            description: "Module \"{$module->name}\" was added",
            subject: $module,
        );
    }

    /**
     * Handle the ProjectModule "deleted" event.
     */
    public function deleted(ProjectModule $module): void
    {
        app(RecordActivity::class)->handle(
            team: $module->project->team,
            project: $module->project,
            userId: $module->deleted_by,
            event: 'module.deleted',
            description: "Module \"{$module->name}\" was deleted",
        );
    }
}

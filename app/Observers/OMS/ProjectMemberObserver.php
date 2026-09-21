<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\ProjectMember;

class ProjectMemberObserver
{
    /**
     * Handle the ProjectMember "created" event.
     */
    public function created(ProjectMember $member): void
    {
        $member->loadMissing(['project.team', 'user:id,name']);

        app(RecordActivity::class)->handle(
            team: $member->project->team,
            project: $member->project,
            userId: $member->created_by,
            event: 'member.added',
            description: "{$member->user->name} was added to the project as {$member->role->label()}",
            subject: $member,
        );
    }

    /**
     * Handle the ProjectMember "deleted" event.
     */
    public function deleted(ProjectMember $member): void
    {
        $member->loadMissing(['project.team', 'user:id,name']);

        app(RecordActivity::class)->handle(
            team: $member->project->team,
            project: $member->project,
            userId: $member->deleted_by,
            event: 'member.removed',
            description: "{$member->user->name} was removed from the project",
        );
    }
}

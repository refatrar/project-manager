<?php

namespace App\Observers\OMS;

use App\Actions\OMS\RecordActivity;
use App\Models\OMS\Task;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     *
     * Status transitions are recorded separately by ChangeTaskStatus, not
     * here, so a task's creation and its first status write aren't logged
     * as two separate events for the same moment.
     */
    public function created(Task $task): void
    {
        $task->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $task->project->team,
            project: $task->project,
            userId: $task->created_by,
            event: 'task.created',
            description: "Task \"{$task->reference()}\" was created",
            subject: $task,
        );
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $task->loadMissing('project.team');

        app(RecordActivity::class)->handle(
            team: $task->project->team,
            project: $task->project,
            userId: $task->deleted_by,
            event: 'task.deleted',
            description: "Task \"{$task->reference()}\" was deleted",
        );
    }
}

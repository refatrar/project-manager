<?php

namespace App\Actions\OMS;

use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    /**
     * Create a task on the project, allocating its per-project number from
     * `projects.next_task_number` inside the same transaction as the insert
     * so two concurrent creates can never collide (DESIGN.md 3.2).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Project $project, User $creator, array $attributes): Task
    {
        return DB::transaction(function () use ($project, $creator, $attributes): Task {
            $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $task = new Task($attributes);
            $task->project_id = $lockedProject->id;
            $task->number = $lockedProject->next_task_number;
            $task->position = $this->nextPosition($lockedProject, $attributes);
            $task->created_by = $creator->id;
            $task->save();

            $lockedProject->increment('next_task_number');

            return $task;
        });
    }

    /**
     * Get the next board position among tasks sharing the same status
     * (freshly created tasks start in their default/backlog status).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function nextPosition(Project $project, array $attributes): int
    {
        $status = $attributes['status'] ?? 'backlog';

        return $project->tasks()->where('status', $status)->max('position') + 1;
    }
}

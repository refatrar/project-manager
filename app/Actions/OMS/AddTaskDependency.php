<?php

namespace App\Actions\OMS;

use App\Enums\TaskDependencyType;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use App\Models\User;
use RuntimeException;

class AddTaskDependency
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
    ) {
        //
    }

    /**
     * Declare a dependency from $task to $relatedTask, rejecting a link
     * that would create a cycle within that dependency type's graph
     * (DESIGN.md known limitation: cycle detection is not expressible as
     * a database constraint, so it happens here on write).
     *
     * @throws RuntimeException when the link would create a cycle
     */
    public function handle(Task $task, Task $relatedTask, TaskDependencyType $type, User $createdBy): TaskDependency
    {
        if ($this->createsCycle($task, $relatedTask, $type)) {
            throw new RuntimeException('That link would create a dependency cycle.');
        }

        $dependency = new TaskDependency([
            'related_task_id' => $relatedTask->id,
            'type' => $type,
        ]);
        $dependency->task_id = $task->id;
        $dependency->created_by = $createdBy->id;
        $dependency->save();

        $task->loadMissing('project.team');
        $this->recordActivity->handle(
            team: $task->project->team,
            project: $task->project,
            userId: $createdBy->id,
            event: 'dependency.added',
            description: "\"{$task->reference()}\" is now {$type->label()} \"{$relatedTask->reference()}\"",
            subject: $dependency,
        );

        return $dependency;
    }

    /**
     * Determine whether linking $task -> $relatedTask of $type would close
     * a cycle, by checking whether $task is already reachable from
     * $relatedTask following only edges of the same type.
     */
    private function createsCycle(Task $task, Task $relatedTask, TaskDependencyType $type): bool
    {
        if ($task->id === $relatedTask->id) {
            return true;
        }

        $edges = TaskDependency::query()
            ->where('type', $type->value)
            ->whereIn('task_id', function ($query) use ($task) {
                $query->select('id')->from('tasks')->where('project_id', $task->project_id);
            })
            ->get(['task_id', 'related_task_id'])
            ->groupBy('task_id')
            ->map(fn ($group) => $group->pluck('related_task_id')->all());

        $visited = [];
        $queue = [$relatedTask->id];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if ($currentId === $task->id) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            foreach ($edges->get($currentId, []) as $nextId) {
                $queue[] = $nextId;
            }
        }

        return false;
    }
}

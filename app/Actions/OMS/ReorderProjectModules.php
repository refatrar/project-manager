<?php

namespace App\Actions\OMS;

use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReorderProjectModules
{
    /**
     * Apply a new position (and optionally a new parent) to each module.
     *
     * @param  array<int, array{id: int, parent_id: ?int, position: int}>  $modules
     *
     * @throws RuntimeException when a move would nest a module under its own descendant
     */
    public function handle(Project $project, array $modules, int $movedBy): void
    {
        DB::transaction(function () use ($project, $modules, $movedBy): void {
            $records = ProjectModule::query()
                ->where('project_id', $project->id)
                ->whereIn('id', array_column($modules, 'id'))
                ->get()
                ->keyBy('id');

            foreach ($modules as $move) {
                $module = $records->get($move['id']);

                if ($module === null) {
                    continue;
                }

                if ($move['parent_id'] !== null && $this->createsCycle($project, $module->id, $move['parent_id'])) {
                    throw new RuntimeException("Moving module {$module->id} under {$move['parent_id']} would create a cycle.");
                }

                $module->parent_id = $move['parent_id'];
                $module->position = $move['position'];
                $module->updated_by = $movedBy;
                $module->save();
            }
        });
    }

    /**
     * Determine whether making $parentId the parent of $moduleId would nest
     * the module under one of its own descendants.
     */
    public function createsCycle(Project $project, int $moduleId, int $parentId): bool
    {
        if ($moduleId === $parentId) {
            return true;
        }

        $parentsById = ProjectModule::query()
            ->where('project_id', $project->id)
            ->pluck('parent_id', 'id');

        $currentId = $parentId;
        $guard = 0;

        while ($currentId !== null && $guard < 1000) {
            if ($currentId === $moduleId) {
                return true;
            }

            $currentId = $parentsById->get($currentId);
            $guard++;
        }

        return false;
    }
}

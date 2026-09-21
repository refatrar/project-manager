<?php

namespace App\Actions\OMS;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class AssignTask
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
    ) {
        //
    }

    /**
     * Assign a user to a task in a given role. `task_assignments` has no
     * soft-delete column, so a previously unassigned (task_id, user_id,
     * role) row is reactivated rather than inserted again — the unique
     * key would otherwise reject a second insert (DESIGN.md 3.3).
     *
     * @throws RuntimeException when the user already actively holds this role
     */
    public function assign(Task $task, int $userId, TaskAssignmentRole $role, ?string $allocatedHours, User $assignedBy): TaskAssignment
    {
        $existing = TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('user_id', $userId)
            ->where('role', $role->value)
            ->first();

        if ($existing !== null && $existing->unassigned_at === null) {
            throw new RuntimeException('This user already holds that role on the task.');
        }

        if ($existing !== null) {
            $existing->status = TaskAssignmentStatus::Assigned;
            $existing->allocated_hours = $allocatedHours;
            $existing->assigned_by = $assignedBy->id;
            $existing->assigned_at = Carbon::now();
            $existing->accepted_at = null;
            $existing->started_at = null;
            $existing->completed_at = null;
            $existing->unassigned_at = null;
            $existing->save();
            $assignment = $existing;
        } else {
            $assignment = new TaskAssignment([
                'user_id' => $userId,
                'role' => $role,
                'status' => TaskAssignmentStatus::Assigned,
                'allocated_hours' => $allocatedHours,
            ]);
            $assignment->task_id = $task->id;
            $assignment->assigned_by = $assignedBy->id;
            $assignment->save();
        }

        $task->loadMissing('project.team');
        $assignedUser = $assignment->user()->first(['id', 'name']);
        $this->recordActivity->handle(
            team: $task->project->team,
            project: $task->project,
            userId: $assignedBy->id,
            event: 'task.assigned',
            description: "{$assignedUser?->name} was assigned as {$role->label()} on \"{$task->reference()}\"",
            subject: $assignment,
        );

        return $assignment;
    }

    /**
     * Remove a user from a task without deleting the row, so the
     * assignment's history (who, when, for how long) survives (RD.md FR-3.5).
     * There is no `unassigned_by` column, so who performed the removal is
     * not persisted on the row — only that it happened and when. $unassignedBy
     * is used solely to attribute the activity log entry.
     */
    public function unassign(TaskAssignment $assignment, User $unassignedBy): TaskAssignment
    {
        $assignment->status = TaskAssignmentStatus::Reassigned;
        $assignment->unassigned_at = Carbon::now();
        $assignment->save();

        $assignment->loadMissing(['task.project.team', 'user:id,name']);
        $this->recordActivity->handle(
            team: $assignment->task->project->team,
            project: $assignment->task->project,
            userId: $unassignedBy->id,
            event: 'task.unassigned',
            description: "{$assignment->user->name} was unassigned from \"{$assignment->task->reference()}\"",
            subject: $assignment,
        );

        return $assignment;
    }
}

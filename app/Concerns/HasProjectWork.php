<?php

namespace App\Concerns;

use App\Models\Git\GitCommit;
use App\Models\Git\GitIdentity;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TimeLog;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\TodoList;
use App\Models\OMS\UserWorkSchedule;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work, capacity, and Git relationships for a user across every project.
 */
trait HasProjectWork
{
    /**
     * Get the projects the user is a member of.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'status', 'allocation_percentage'])
            ->withTimestamps();
    }

    /**
     * Get the membership records for the user.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get every task assignment held by the user.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the tasks assigned to the user across all projects.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignments')
            ->withPivot(['role', 'status', 'allocated_hours', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the to-do lists owned by the user.
     *
     * @return HasMany<TodoList, $this>
     */
    public function todoLists(): HasMany
    {
        return $this->hasMany(TodoList::class, 'owner_id');
    }

    /**
     * Get the time the user has logged.
     *
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the user's recurring weekly capacity rows.
     *
     * @return HasMany<UserWorkSchedule, $this>
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(UserWorkSchedule::class);
    }

    /**
     * Get the user's time off requests.
     *
     * @return HasMany<TimeOffRequest, $this>
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(TimeOffRequest::class);
    }

    /**
     * Get the forward bookings that occupy the user's hours.
     *
     * @return HasMany<ResourceAllocation, $this>
     */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    /**
     * Get the user's linked Git provider accounts.
     *
     * @return HasMany<GitIdentity, $this>
     */
    public function gitIdentities(): HasMany
    {
        return $this->hasMany(GitIdentity::class);
    }

    /**
     * Get the commits attributed to the user.
     *
     * @return HasMany<GitCommit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(GitCommit::class, 'author_id');
    }
}

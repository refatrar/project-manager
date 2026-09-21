<?php

namespace App\Models\OMS;

use App\Enums\DeploymentStage;
use App\Enums\Priority;
use App\Enums\TaskAssignmentRole;
use App\Enums\TaskReviewStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Git\GitBranch;
use App\Models\Git\GitCommit;
use App\Models\Git\GitPullRequest;
use App\Models\Setup\Label;
use App\Models\Setup\TaskType;
use App\Models\User;
use Database\Factories\OMS\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $project_module_id
 * @property int|null $parent_id
 * @property int $task_type_id
 * @property int|null $milestone_id
 * @property int|null $sprint_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskReviewStatus|null $review_status
 * @property DeploymentStage|null $deployment_stage
 * @property Priority $priority
 * @property string|null $estimated_hours
 * @property string $logged_hours
 * @property string|null $remaining_hours
 * @property int $progress_percentage
 * @property int $position
 * @property bool $is_billable
 * @property Carbon|null $starts_at
 * @property Carbon|null $due_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $closed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read ProjectModule|null $module
 * @property-read Task|null $parent
 * @property-read Collection<int, Task> $subtasks
 * @property-read TaskType $taskType
 * @property-read Milestone|null $milestone
 * @property-read Sprint|null $sprint
 * @property-read Collection<int, TaskAssignment> $assignments
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, TaskDependency> $dependencies
 * @property-read Collection<int, TaskStatusHistory> $statusHistories
 * @property-read Collection<int, Label> $labels
 * @property-read Collection<int, TimeLog> $timeLogs
 * @property-read Collection<int, TodoList> $checklists
 * @property-read Collection<int, GitCommit> $commits
 * @property-read Collection<int, GitBranch> $branches
 * @property-read Collection<int, GitPullRequest> $pullRequests
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable([
    'project_module_id', 'parent_id', 'task_type_id', 'milestone_id', 'sprint_id',
    'title', 'description', 'status', 'review_status', 'deployment_stage', 'priority',
    'estimated_hours', 'remaining_hours', 'position', 'is_billable', 'starts_at', 'due_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the human readable reference, for example "ACME-114".
     */
    public function reference(): string
    {
        return $this->project->code.'-'.$this->number;
    }

    /**
     * Get the project the task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the module the task is grouped under, when one is set.
     *
     * @return BelongsTo<ProjectModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(ProjectModule::class, 'project_module_id');
    }

    /**
     * Get the parent task.
     *
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the subtasks of the task.
     *
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get the configured task type.
     *
     * @return BelongsTo<TaskType, $this>
     */
    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * Get the milestone the task contributes to.
     *
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Get the sprint the task is committed to.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get every assignment record for the task.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the users assigned to deliver the task.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments')
            ->withPivot(['role', 'status', 'allocated_hours', 'assigned_at'])
            ->wherePivot('role', TaskAssignmentRole::Assignee->value)
            ->withTimestamps();
    }

    /**
     * Get the dependency links declared on the task.
     *
     * @return HasMany<TaskDependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    /**
     * Get the status transition trail used for cycle time reporting.
     *
     * @return HasMany<TaskStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class);
    }

    /**
     * Get the labels applied to the task.
     *
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    /**
     * Get the time logged against the task.
     *
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the checklists attached to the task.
     *
     * @return HasMany<TodoList, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TodoList::class);
    }

    /**
     * Get the commits linked to the task.
     *
     * @return BelongsToMany<GitCommit, $this>
     */
    public function commits(): BelongsToMany
    {
        return $this->belongsToMany(GitCommit::class, 'git_commit_task')
            ->withPivot(['link_source']);
    }

    /**
     * Get the branches opened for the task.
     *
     * @return HasMany<GitBranch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(GitBranch::class);
    }

    /**
     * Get the pull requests raised for the task.
     *
     * @return HasMany<GitPullRequest, $this>
     */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(GitPullRequest::class);
    }

    /**
     * Get the discussion on the task.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the files attached to the task.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Scope the query to tasks that are not finished or cancelled.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]);
    }

    /**
     * Scope the query to tasks past their due date and still open.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /**
     * Scope the query to tasks assigned to the given user.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('assignments', fn (Builder $assignments) => $assignments
            ->where('user_id', $user->id)
            ->whereNull('unassigned_at'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'review_status' => TaskReviewStatus::class,
            'deployment_stage' => DeploymentStage::class,
            'priority' => Priority::class,
            'is_billable' => 'boolean',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the payload used for a Kanban board card. Assumes `taskType`,
     * `assignees`, `assignments.user` and `labels` are loaded.
     *
     * @return array<string, mixed>
     */
    public function toBoardArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_module_id' => $this->project_module_id,
            'milestone_id' => $this->milestone_id,
            'sprint_id' => $this->sprint_id,
            'reference' => $this->reference(),
            'title' => $this->title,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'position' => $this->position,
            'due_at' => $this->due_at?->toIso8601String(),
            'taskType' => [
                'id' => $this->taskType->id,
                'name' => $this->taskType->name,
            ],
            'assignees' => $this->assignees->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])->values(),
            'assignments' => $this->assignments
                ->filter(fn (TaskAssignment $assignment): bool => $assignment->unassigned_at === null)
                ->map(fn (TaskAssignment $assignment): array => $assignment->toListArray())
                ->values(),
            'labels' => $this->labels->map(fn (Label $label): array => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ])->values(),
        ];
    }

    /**
     * Get the full payload used for the task detail page.
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        return [
            ...$this->toBoardArray(),
            'description' => $this->description,
            'review_status' => $this->review_status?->value,
            'deployment_stage' => $this->deployment_stage?->value,
            'estimated_hours' => $this->estimated_hours,
            'logged_hours' => $this->logged_hours,
            'remaining_hours' => $this->remaining_hours,
            'progress_percentage' => $this->progress_percentage,
            'is_billable' => $this->is_billable,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}

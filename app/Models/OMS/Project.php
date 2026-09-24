<?php

namespace App\Models\OMS;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Git\GitRepository;
use App\Models\Team;
use App\Models\User;
use Database\Factories\OMS\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property Priority $priority
 * @property ProjectHealth $health
 * @property string|null $color
 * @property string|null $client_name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $actual_start_date
 * @property Carbon|null $actual_end_date
 * @property string|null $estimated_hours
 * @property string|null $budget
 * @property string|null $currency
 * @property int $progress_percentage
 * @property int $next_task_number
 * @property int|null $owner_id
 * @property int|null $project_lead_id
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $owner
 * @property-read User|null $projectLead
 * @property-read Collection<int, ProjectModule> $modules
 * @property-read Collection<int, ProjectMember> $members
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Milestone> $milestones
 * @property-read Collection<int, Sprint> $sprints
 * @property-read Collection<int, Meeting> $meetings
 * @property-read Collection<int, TimeLog> $timeLogs
 * @property-read Collection<int, ResourceAllocation> $resourceAllocations
 * @property-read Collection<int, ProjectProgressSnapshot> $progressSnapshots
 * @property-read Collection<int, GitRepository> $gitRepositories
 * @property-read Collection<int, Activity> $activities
 */
#[Fillable([
    'code', 'slug', 'name', 'description', 'status', 'priority', 'health', 'color',
    'client_name', 'start_date', 'end_date', 'actual_start_date', 'actual_end_date',
    'estimated_hours', 'budget', 'currency', 'owner_id', 'project_lead_id',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the display reference used for tasks, for example "ACME".
     */
    public function reference(): string
    {
        return $this->code;
    }

    /**
     * Get the team that owns the project.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user accountable for the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user with administrative authority over this project only
     * (task creation/assignment, meeting/attendee management), distinct
     * from team-wide authority and from `ProjectMemberRole`.
     *
     * @return BelongsTo<User, $this>
     */
    public function projectLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_lead_id');
    }

    /**
     * Get the modules that break down the project.
     *
     * @return HasMany<ProjectModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(ProjectModule::class);
    }

    /**
     * Get the top level modules of the project.
     *
     * @return HasMany<ProjectModule, $this>
     */
    public function rootModules(): HasMany
    {
        return $this->modules()->whereNull('parent_id');
    }

    /**
     * Get the membership records for the project.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get the users that are members of the project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'status', 'allocation_percentage'])
            ->withTimestamps();
    }

    /**
     * Get every task that belongs to the project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the milestones of the project.
     *
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * Get the sprints of the project.
     *
     * @return HasMany<Sprint, $this>
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * Get the meetings held for the project.
     *
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Get the time logged against the project.
     *
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the planned capacity bookings for the project.
     *
     * @return HasMany<ResourceAllocation, $this>
     */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    /**
     * Get the daily progress snapshots used for burndown reporting.
     *
     * @return HasMany<ProjectProgressSnapshot, $this>
     */
    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(ProjectProgressSnapshot::class);
    }

    /**
     * Get the repositories linked to the project.
     *
     * @return HasMany<GitRepository, $this>
     */
    public function gitRepositories(): HasMany
    {
        return $this->hasMany(GitRepository::class);
    }

    /**
     * Get the activity feed entries recorded against the project.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Scope the query to projects that are not archived or closed.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProjectStatus::Planning->value,
            ProjectStatus::Active->value,
            ProjectStatus::OnHold->value,
        ])->whereNull('archived_at');
    }

    /**
     * Scope the query to projects the given user is an active member of.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    #[Scope]
    protected function forMember(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn (Builder $members) => $members
            ->where('user_id', $user->id)
            ->where('status', 'active'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'health' => ProjectHealth::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the lightweight payload used for project list rows.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'health' => $this->health->value,
            'progress_percentage' => $this->progress_percentage,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }

    /**
     * Get the full payload used for the project workspace.
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        return [
            ...$this->toListArray(),
            'description' => $this->description,
            'color' => $this->color,
            'client_name' => $this->client_name,
            'actual_start_date' => $this->actual_start_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'estimated_hours' => $this->estimated_hours,
            'budget' => $this->budget,
            'currency' => $this->currency,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null,
            'project_lead' => $this->projectLead ? [
                'id' => $this->projectLead->id,
                'name' => $this->projectLead->name,
            ] : null,
        ];
    }
}

<?php

namespace App\Models\OMS;

use App\Enums\Priority;
use App\Enums\ProjectModuleStatus;
use App\Models\Concerns\HasAuditUsers;
use App\Models\Setup\Scope as ProjectScope;
use Database\Factories\OMS\ProjectModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property int $project_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property ProjectModuleStatus $status
 * @property Priority $priority
 * @property int $position
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $estimated_hours
 * @property int $progress_percentage
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read ProjectModule|null $parent
 * @property-read Collection<int, ProjectModule> $children
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, ProjectScope> $deliveryScopes
 */
#[Fillable([
    'parent_id', 'name', 'description', 'status', 'priority', 'position',
    'start_date', 'end_date', 'estimated_hours',
])]
class ProjectModule extends Model
{
    /** @use HasFactory<ProjectModuleFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the project the module belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent module.
     *
     * @return BelongsTo<ProjectModule, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the nested sub modules.
     *
     * @return HasMany<ProjectModule, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get the tasks that belong to the module.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the delivery scopes covered by the module.
     *
     * Named `deliveryScopes` because `scopes` would shadow Eloquent's query-builder passthrough.
     *
     * @return BelongsToMany<ProjectScope, $this>
     */
    public function deliveryScopes(): BelongsToMany
    {
        return $this->belongsToMany(ProjectScope::class, 'project_module_scope');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'status' => ProjectModuleStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the payload used for the project's module tree.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'position' => $this->position,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'estimated_hours' => $this->estimated_hours,
            'progress_percentage' => $this->progress_percentage,
        ];
    }
}

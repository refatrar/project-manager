<?php

namespace App\Models\OMS;

use App\Enums\ProjectHealth;
use Database\Factories\OMS\ProjectProgressSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $sprint_id
 * @property Carbon $snapshot_on
 * @property int $total_tasks
 * @property int $completed_tasks
 * @property int $in_progress_tasks
 * @property int $blocked_tasks
 * @property int $overdue_tasks
 * @property string $estimated_hours
 * @property string $logged_hours
 * @property string $remaining_hours
 * @property string $progress_percentage
 * @property int $commits_count
 * @property int $merged_pull_requests_count
 * @property ProjectHealth $health
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Sprint|null $sprint
 */
#[Fillable([
    'sprint_id', 'snapshot_on', 'total_tasks', 'completed_tasks', 'in_progress_tasks',
    'blocked_tasks', 'overdue_tasks', 'estimated_hours', 'logged_hours', 'remaining_hours',
    'progress_percentage', 'commits_count', 'merged_pull_requests_count', 'health',
])]
class ProjectProgressSnapshot extends Model
{
    /** @use HasFactory<ProjectProgressSnapshotFactory> */
    use HasFactory;

    /**
     * Get the project the snapshot describes.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the sprint the snapshot is scoped to, when any.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_on' => 'date',
            'health' => ProjectHealth::class,
        ];
    }
}

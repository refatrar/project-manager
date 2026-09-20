<?php

namespace App\Models\Git;

use App\Models\OMS\Task;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $git_repository_id
 * @property int|null $task_id
 * @property string $name
 * @property string|null $head_commit_sha
 * @property bool $is_default
 * @property bool $is_protected
 * @property bool $is_merged
 * @property int|null $ahead_count
 * @property int|null $behind_count
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GitRepository $repository
 * @property-read Task|null $task
 */
#[Fillable([
    'task_id', 'name', 'head_commit_sha', 'is_default', 'is_protected', 'is_merged',
    'ahead_count', 'behind_count', 'last_activity_at',
])]
class GitBranch extends Model
{
    /**
     * Get the repository the branch belongs to.
     *
     * @return BelongsTo<GitRepository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitRepository::class, 'git_repository_id');
    }

    /**
     * Get the task the branch was created for.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_protected' => 'boolean',
            'is_merged' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }
}

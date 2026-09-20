<?php

namespace App\Models\Git;

use App\Enums\PullRequestState;
use App\Models\OMS\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $git_repository_id
 * @property int|null $task_id
 * @property int $number
 * @property string|null $external_id
 * @property string $title
 * @property string|null $description
 * @property PullRequestState $state
 * @property string $source_branch
 * @property string $target_branch
 * @property string|null $author_name
 * @property int|null $author_id
 * @property int|null $merged_by
 * @property int $commits_count
 * @property int|null $additions
 * @property int|null $deletions
 * @property int|null $changed_files
 * @property int $review_comments_count
 * @property string|null $web_url
 * @property Carbon|null $opened_at
 * @property Carbon|null $merged_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GitRepository $repository
 * @property-read Task|null $task
 * @property-read User|null $author
 * @property-read User|null $merger
 */
#[Fillable([
    'task_id', 'number', 'external_id', 'title', 'description', 'state', 'source_branch',
    'target_branch', 'author_name', 'author_id', 'merged_by', 'commits_count', 'additions',
    'deletions', 'changed_files', 'review_comments_count', 'web_url', 'opened_at',
    'merged_at', 'closed_at',
])]
class GitPullRequest extends Model
{
    /**
     * Get the repository the pull request belongs to.
     *
     * @return BelongsTo<GitRepository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitRepository::class, 'git_repository_id');
    }

    /**
     * Get the task the pull request delivers.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the resolved application user who opened the pull request.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the user who merged the pull request.
     *
     * @return BelongsTo<User, $this>
     */
    public function merger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PullRequestState::class,
            'opened_at' => 'datetime',
            'merged_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}

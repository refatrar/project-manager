<?php

namespace App\Models\Git;

use App\Models\OMS\Task;
use App\Models\User;
use Database\Factories\Git\GitCommitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $git_repository_id
 * @property string $sha
 * @property string|null $parent_sha
 * @property string $message
 * @property string|null $branch
 * @property string|null $author_name
 * @property string|null $author_email
 * @property int|null $author_id
 * @property string|null $committer_name
 * @property string|null $committer_email
 * @property int|null $additions
 * @property int|null $deletions
 * @property int|null $changed_files
 * @property string|null $web_url
 * @property Carbon|null $authored_at
 * @property Carbon $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GitRepository $repository
 * @property-read User|null $author
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable([
    'sha', 'parent_sha', 'message', 'branch', 'author_name', 'author_email', 'author_id',
    'committer_name', 'committer_email', 'additions', 'deletions', 'changed_files',
    'web_url', 'authored_at', 'committed_at',
])]
class GitCommit extends Model
{
    /** @use HasFactory<GitCommitFactory> */
    use HasFactory;

    /**
     * Get the short SHA used in the interface.
     */
    public function shortSha(): string
    {
        return substr($this->sha, 0, 8);
    }

    /**
     * Get the repository the commit belongs to.
     *
     * @return BelongsTo<GitRepository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitRepository::class, 'git_repository_id');
    }

    /**
     * Get the resolved application user who authored the commit.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the tasks referenced by the commit.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'git_commit_task')
            ->withPivot(['link_source']);
    }

    /**
     * Scope the query to commits pushed within an inclusive datetime range.
     *
     * @param  Builder<GitCommit>  $query
     * @return Builder<GitCommit>
     */
    #[Scope]
    protected function committedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('committed_at', [$from, $to]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authored_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }
}

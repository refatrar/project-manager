<?php

namespace App\Models\Git;

use App\Enums\GitProvider;
use App\Enums\GitSyncStatus;
use App\Models\Concerns\HasAuditUsers;
use App\Models\OMS\Project;
use App\Models\Team;
use Database\Factories\Git\GitRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property GitProvider $provider
 * @property string $full_name
 * @property string|null $external_id
 * @property string $default_branch
 * @property string|null $visibility
 * @property string|null $web_url
 * @property string|null $clone_url
 * @property string|null $api_base_url
 * @property string|null $access_token
 * @property string|null $webhook_secret
 * @property string|null $webhook_external_id
 * @property GitSyncStatus $sync_status
 * @property Carbon|null $last_synced_at
 * @property string|null $last_sync_error
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read Collection<int, GitBranch> $branches
 * @property-read Collection<int, GitCommit> $commits
 * @property-read Collection<int, GitPullRequest> $pullRequests
 * @property-read Collection<int, GitEvent> $events
 */
#[Fillable([
    'project_id', 'provider', 'full_name', 'external_id', 'default_branch', 'visibility',
    'web_url', 'clone_url', 'api_base_url', 'access_token', 'webhook_secret',
    'webhook_external_id', 'is_active',
])]
class GitRepository extends Model
{
    /** @use HasFactory<GitRepositoryFactory> */
    use HasAuditUsers, HasFactory, SoftDeletes;

    /**
     * Get the team that connected the repository.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project the repository is linked to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tracked branches.
     *
     * @return HasMany<GitBranch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(GitBranch::class);
    }

    /**
     * Get the ingested commits.
     *
     * @return HasMany<GitCommit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(GitCommit::class);
    }

    /**
     * Get the ingested pull requests.
     *
     * @return HasMany<GitPullRequest, $this>
     */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(GitPullRequest::class);
    }

    /**
     * Get the raw webhook and push events received for the repository.
     *
     * @return HasMany<GitEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GitEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GitProvider::class,
            'sync_status' => GitSyncStatus::class,
            'access_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}

<?php

namespace Tests\Feature\Git;

use App\Enums\CommitLinkSource;
use App\Models\Git\GitCommit;
use App\Models\Git\GitRepository;
use App\Models\OMS\Task;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GitCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_commit_sha_is_unique_within_a_repository(): void
    {
        $repository = GitRepository::factory()->create();
        GitCommit::factory()->for($repository, 'repository')->create(['sha' => str_repeat('a', 40)]);

        $this->expectException(QueryException::class);

        GitCommit::factory()->for($repository, 'repository')->create(['sha' => str_repeat('a', 40)]);
    }

    public function test_the_same_sha_can_exist_in_two_repositories(): void
    {
        $sha = str_repeat('b', 40);

        GitCommit::factory()->for(GitRepository::factory()->create(), 'repository')->create(['sha' => $sha]);
        GitCommit::factory()->for(GitRepository::factory()->create(), 'repository')->create(['sha' => $sha]);

        $this->assertSame(2, GitCommit::where('sha', $sha)->count());
    }

    public function test_one_commit_can_reference_several_tasks(): void
    {
        $commit = GitCommit::factory()->create();
        $first = Task::factory()->create();
        $second = Task::factory()->create();

        $commit->tasks()->attach($first->id, ['link_source' => CommitLinkSource::CommitMessage->value]);
        $commit->tasks()->attach($second->id, ['link_source' => CommitLinkSource::BranchName->value]);

        $this->assertCount(2, $commit->tasks);
        $this->assertCount(1, $first->commits);
    }

    public function test_a_repository_access_token_is_stored_encrypted(): void
    {
        $repository = GitRepository::factory()->create(['access_token' => 'ghp_secret_value']);

        $this->assertSame('ghp_secret_value', $repository->fresh()->access_token);
        $this->assertNotSame(
            'ghp_secret_value',
            DB::table('git_repositories')->where('id', $repository->id)->value('access_token'),
        );
    }

    public function test_deleting_a_repository_removes_its_commits(): void
    {
        $repository = GitRepository::factory()->create();
        GitCommit::factory()->for($repository, 'repository')->create();

        $repository->forceDelete();

        $this->assertSame(0, GitCommit::count());
    }
}

<?php

namespace Tests\Feature\OMS;

use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_be_added_to_the_same_project_twice(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        ProjectMember::factory()->for($project)->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        ProjectMember::factory()->for($project)->create(['user_id' => $user->id]);
    }

    public function test_a_user_can_be_a_member_of_many_projects(): void
    {
        $user = User::factory()->create();

        ProjectMember::factory()->for(Project::factory()->create())->create(['user_id' => $user->id]);
        ProjectMember::factory()->for(Project::factory()->create())->manager()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->projects);
    }

    public function test_the_active_scope_excludes_members_who_have_left(): void
    {
        $project = Project::factory()->create();
        $current = ProjectMember::factory()->for($project)->create(['user_id' => User::factory()]);
        ProjectMember::factory()->for($project)->inactive()->create(['user_id' => User::factory()]);

        $this->assertSame([$current->id], ProjectMember::active()->pluck('id')->all());
    }

    public function test_the_for_member_scope_only_returns_projects_the_user_works_on(): void
    {
        $user = User::factory()->create();
        $member = ProjectMember::factory()->create(['user_id' => $user->id]);
        Project::factory()->create();

        $this->assertSame([$member->project_id], Project::forMember($user)->pluck('id')->all());
    }
}

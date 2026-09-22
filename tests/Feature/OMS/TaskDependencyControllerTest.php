<?php

namespace Tests\Feature\OMS;

use App\Enums\TaskDependencyType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDependencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dependency_can_be_created_between_two_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $blocker = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $task), [
                'related_task_id' => $blocker->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('dependency.relatedTask.id', $blocker->id);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task->id,
            'related_task_id' => $blocker->id,
            'type' => TaskDependencyType::BlockedBy->value,
        ]);
    }

    public function test_a_task_cannot_depend_on_itself(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $task), [
                'related_task_id' => $task->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['related_task_id']);
    }

    public function test_a_task_from_another_project_cannot_be_linked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $foreignTask = Task::factory()->for($otherProject)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $task), [
                'related_task_id' => $foreignTask->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['related_task_id']);
    }

    public function test_the_exact_same_dependency_cannot_be_declared_twice(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $blocker = Task::factory()->for($project)->create();
        TaskDependency::factory()->create([
            'task_id' => $task->id,
            'related_task_id' => $blocker->id,
            'type' => TaskDependencyType::BlockedBy,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $task), [
                'related_task_id' => $blocker->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['related_task_id']);
    }

    public function test_a_direct_two_task_cycle_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        TaskDependency::factory()->create([
            'task_id' => $taskA->id,
            'related_task_id' => $taskB->id,
            'type' => TaskDependencyType::BlockedBy,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $taskB), [
                'related_task_id' => $taskA->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('task_dependencies', 1);
    }

    public function test_an_indirect_three_task_cycle_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        $taskC = Task::factory()->for($project)->create();

        TaskDependency::factory()->create([
            'task_id' => $taskA->id, 'related_task_id' => $taskB->id, 'type' => TaskDependencyType::BlockedBy,
        ]);
        TaskDependency::factory()->create([
            'task_id' => $taskB->id, 'related_task_id' => $taskC->id, 'type' => TaskDependencyType::BlockedBy,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $taskC), [
                'related_task_id' => $taskA->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('task_dependencies', 2);
    }

    public function test_a_different_dependency_type_does_not_trigger_a_false_cycle(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        TaskDependency::factory()->create([
            'task_id' => $taskA->id, 'related_task_id' => $taskB->id, 'type' => TaskDependencyType::BlockedBy,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $project, $taskB), [
                'related_task_id' => $taskA->id,
                'type' => TaskDependencyType::RelatesTo->value,
            ]);

        $response->assertCreated();
    }

    public function test_a_dependency_can_be_removed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $dependency = TaskDependency::factory()->create(['task_id' => $task->id]);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->dependencyRoute($user, 'projects.tasks.dependencies.destroy', $project, $task, $dependency));

        $response->assertOk();
        $this->assertDatabaseCount('task_dependencies', 0);
    }

    public function test_a_project_from_another_team_cannot_be_reached_through_the_users_own_team_url(): void
    {
        $user = User::factory()->create();
        $foreignProject = Project::factory()->create();
        $foreignTask = Task::factory()->for($foreignProject)->create();
        $blocker = Task::factory()->for($foreignProject)->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->dependencyRoute($user, 'projects.tasks.dependencies.store', $foreignProject, $foreignTask), [
                'related_task_id' => $blocker->id,
                'type' => TaskDependencyType::BlockedBy->value,
            ]);

        $response->assertNotFound();
    }

    private function dependencyRoute(User $user, string $name, Project $project, Task $task, ?TaskDependency $dependency = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'project' => $project,
            'task' => $task,
            'dependency' => $dependency,
        ]));
    }
}

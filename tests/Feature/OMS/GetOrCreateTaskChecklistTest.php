<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\GetOrCreateTaskChecklist;
use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetOrCreateTaskChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_checklist_is_created_the_first_time_it_is_requested(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $checklist = app(GetOrCreateTaskChecklist::class)->handle($task, $project);

        $this->assertSame(TodoListType::TaskChecklist, $checklist->type);
        $this->assertSame($task->id, $checklist->task_id);
        $this->assertSame($project->id, $checklist->project_id);
        $this->assertSame($project->team_id, $checklist->team_id);
        $this->assertNull($checklist->owner_id);
    }

    public function test_requesting_it_again_returns_the_same_checklist(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $first = app(GetOrCreateTaskChecklist::class)->handle($task, $project);
        $second = app(GetOrCreateTaskChecklist::class)->handle($task, $project);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('todo_lists', 1);
    }

    public function test_each_task_gets_its_own_checklist(): void
    {
        $project = Project::factory()->create();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();

        $checklistA = app(GetOrCreateTaskChecklist::class)->handle($taskA, $project);
        $checklistB = app(GetOrCreateTaskChecklist::class)->handle($taskB, $project);

        $this->assertNotSame($checklistA->id, $checklistB->id);
    }
}

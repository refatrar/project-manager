<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\GenerateDailyTodoList;
use App\Enums\TaskStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GenerateDailyTodoListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_open_tasks_assigned_to_the_user_due_within_a_week(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->addDays(3),
        ]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertSame(TodoListType::Generated, $list->type);
        $this->assertCount(1, $list->items);
        $this->assertSame($task->id, $list->items->first()->task_id);
    }

    public function test_it_excludes_tasks_due_further_out_than_a_week(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->addDays(30),
        ]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertCount(0, $list->items);
    }

    public function test_it_includes_tasks_with_no_due_date(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'due_at' => null]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertCount(1, $list->items);
    }

    public function test_it_excludes_done_and_cancelled_tasks(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $done = Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'due_at' => now()]);
        $cancelled = Task::factory()->for($project)->create(['status' => TaskStatus::Cancelled, 'due_at' => now()]);
        TaskAssignment::factory()->for($done)->create(['user_id' => $user->id]);
        TaskAssignment::factory()->for($cancelled)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertCount(0, $list->items);
    }

    public function test_it_excludes_tasks_the_user_has_been_unassigned_from(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'due_at' => now()]);
        TaskAssignment::factory()->reassigned()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertCount(0, $list->items);
    }

    public function test_it_excludes_tasks_from_another_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $otherProject = Project::factory()->create();
        $task = Task::factory()->for($otherProject)->create(['status' => TaskStatus::Todo, 'due_at' => now()]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertCount(0, $list->items);
    }

    public function test_calling_it_twice_for_the_same_day_returns_the_same_list(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $first = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());
        $second = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('todo_lists', 1);
    }

    public function test_a_task_becoming_done_after_generation_does_not_retroactively_remove_it(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'due_at' => now()]);
        TaskAssignment::factory()->for($task)->create(['user_id' => $user->id]);

        $list = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());
        $this->assertCount(1, $list->items);

        $task->status = TaskStatus::Done;
        $task->save();

        $sameList = app(GenerateDailyTodoList::class)->handle($user, $team, Carbon::now());
        $this->assertCount(1, $sameList->items);
    }
}

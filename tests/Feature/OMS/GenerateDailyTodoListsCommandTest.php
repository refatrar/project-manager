<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Enums\TodoListType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateDailyTodoListsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_list_for_every_member_of_every_team(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->artisan('oms:generate-daily-todo-lists')->assertSuccessful();

        $this->assertDatabaseHas('todo_lists', [
            'team_id' => $owner->currentTeam->id,
            'owner_id' => $owner->id,
            'type' => TodoListType::Generated->value,
        ]);
        $this->assertDatabaseHas('todo_lists', [
            'team_id' => $owner->currentTeam->id,
            'owner_id' => $member->id,
            'type' => TodoListType::Generated->value,
        ]);
    }

    public function test_running_it_twice_does_not_duplicate_lists(): void
    {
        User::factory()->create();

        $this->artisan('oms:generate-daily-todo-lists')->assertSuccessful();
        $this->artisan('oms:generate-daily-todo-lists')->assertSuccessful();

        $this->assertDatabaseCount('todo_lists', 1);
    }
}

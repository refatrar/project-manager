<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Enums\TodoListType;
use App\Models\OMS\Task;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TodoListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_only_shows_the_users_own_personal_and_daily_lists(): void
    {
        $user = User::factory()->create();
        $ownList = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
            'name' => 'My list',
        ]);
        TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => User::factory()->create()->id,
            'name' => 'Someone elses list',
        ]);
        TodoList::factory()->checklistFor(Task::factory()->create())->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->todoListsRoute($user, 'todo-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('todo-lists/index')
            // 2, not 1: the index visit itself generates the user's daily
            // list of tasks due soon (FR-5.6), alongside their own "My list".
            ->has('lists', 2)
            ->where('lists', fn (Collection $lists): bool => $lists->pluck('id')->contains($ownList->id)),
        );
    }

    public function test_a_custom_list_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->todoListsRoute($user, 'todo-lists.store'), [
                'name' => 'Groceries',
                'type' => TodoListType::Custom->value,
                'status' => 'open',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('list.name', 'Groceries');

        $this->assertDatabaseHas('todo_lists', [
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
            'name' => 'Groceries',
            'type' => TodoListType::Custom->value,
        ]);
    }

    public function test_a_daily_list_requires_a_scheduled_date(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->todoListsRoute($user, 'todo-lists.store'), [
                'name' => "Today's plan",
                'type' => TodoListType::Daily->value,
                'status' => 'open',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_for']);
    }

    public function test_a_list_cannot_be_created_with_a_system_managed_type(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->todoListsRoute($user, 'todo-lists.store'), [
                'name' => 'Sneaky checklist',
                'type' => TodoListType::TaskChecklist->value,
                'status' => 'open',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_a_user_can_update_their_own_list(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->todoListsRoute($user, 'todo-lists.update', $list), [
                'name' => 'Renamed',
                'status' => 'open',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('todo_lists', ['id' => $list->id, 'name' => 'Renamed']);
    }

    public function test_a_user_cannot_update_someone_elses_list(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $user->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->todoListsRoute($user, 'todo-lists.update', $list), [
                'name' => 'Renamed',
                'status' => 'open',
            ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_list(): void
    {
        $user = User::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $user->currentTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->todoListsRoute($user, 'todo-lists.destroy', $list));

        $response->assertOk();
        $this->assertSoftDeleted('todo_lists', ['id' => $list->id]);
    }

    public function test_a_list_cannot_be_reached_through_another_teams_url(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $list = TodoList::factory()->create([
            'team_id' => $otherTeam->id,
            'owner_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->todoListsRoute($user, 'todo-lists.update', $list), [
                'name' => 'Renamed',
                'status' => 'open',
            ]);

        $response->assertNotFound();
    }

    private function todoListsRoute(User $user, string $name, ?TodoList $list = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'todo_list' => $list,
        ]));
    }
}

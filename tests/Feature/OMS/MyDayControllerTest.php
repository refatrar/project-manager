<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoItem;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyDayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_users_own_lists_and_open_checklist_items_assigned_to_them(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $ownList = TodoList::factory()->create(['team_id' => $team->id, 'owner_id' => $user->id]);

        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $team->id]);
        $assignedItem = TodoItem::factory()->for($checklist, 'list')->create([
            'assigned_to' => $user->id,
            'title' => 'Review the PR',
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->myDayRoute($user));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('my-day/index')
            ->has('checklistItems', 1)
            ->where('checklistItems.0.id', $assignedItem->id)
            // 2, not 1: the visit itself generates the user's daily list of
            // tasks due soon (FR-5.6), alongside their own $ownList.
            ->has('todoLists', 2)
            ->where('todoLists', fn (Collection $lists): bool => $lists->pluck('id')->contains($ownList->id)),
        );
    }

    public function test_it_excludes_completed_checklist_items(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $team->id]);
        TodoItem::factory()->for($checklist, 'list')->create([
            'assigned_to' => $user->id,
            'is_completed' => true,
        ]);

        $response = $this->actingAs($user)->get($this->myDayRoute($user));

        $response->assertInertia(fn (Assert $page) => $page->has('checklistItems', 0));
    }

    public function test_it_excludes_checklist_items_assigned_to_someone_else(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $other = User::factory()->create();
        $team->members()->attach($other, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $team->id]);
        TodoItem::factory()->for($checklist, 'list')->create(['assigned_to' => $other->id]);

        $response = $this->actingAs($user)->get($this->myDayRoute($user));

        $response->assertInertia(fn (Assert $page) => $page->has('checklistItems', 0));
    }

    public function test_it_excludes_items_from_another_team(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->create();
        $task = Task::factory()->for($otherProject)->create();
        $checklist = TodoList::factory()->checklistFor($task)->create(['team_id' => $otherProject->team_id]);
        TodoItem::factory()->for($checklist, 'list')->create(['assigned_to' => $user->id]);

        $response = $this->actingAs($user)->get($this->myDayRoute($user));

        $response->assertInertia(fn (Assert $page) => $page->has('checklistItems', 0));
    }

    public function test_it_does_not_show_a_personal_item_assigned_to_the_user(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $list = TodoList::factory()->create(['team_id' => $team->id, 'owner_id' => $user->id]);
        TodoItem::factory()->for($list, 'list')->create(['assigned_to' => $user->id]);

        $response = $this->actingAs($user)->get($this->myDayRoute($user));

        // Personal-list items surface via `todoLists`, not `checklistItems`.
        $response->assertInertia(fn (Assert $page) => $page->has('checklistItems', 0));
    }

    private function myDayRoute(User $user, ?Team $team = null): string
    {
        return route('my-day', ['current_team' => ($team ?? $user->currentTeam)->slug]);
    }
}

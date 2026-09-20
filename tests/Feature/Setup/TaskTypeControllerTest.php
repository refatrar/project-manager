<?php

namespace Tests\Feature\Setup;

use App\Enums\TaskTypeStatus;
use App\Models\Setup\TaskType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->get($this->taskTypesRoute($user, 'setup.task-types.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_task_types_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create([
            'name' => 'Bug',
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->taskTypesRoute($user, 'setup.task-types.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('setup/task-types/index')
            ->has('taskTypes.data', 1)
            ->where('taskTypes.data.0.id', $taskType->id)
            ->where('taskTypes.data.0.name', 'Bug')
            ->has('statusOptions', 2),
        );
    }

    public function test_the_task_types_index_page_paginates_results(): void
    {
        $user = User::factory()->create();
        TaskType::factory()
            ->count(16)
            ->sequence(fn (Sequence $sequence) => ['name' => 'Task Type '.$sequence->index])
            ->create();

        $firstPage = $this
            ->actingAs($user)
            ->get($this->taskTypesRoute($user, 'setup.task-types.index'));

        $firstPage->assertOk();
        $firstPage->assertInertia(fn (Assert $page) => $page
            ->component('setup/task-types/index')
            ->has('taskTypes.data', 15)
            ->where('taskTypes.per_page', 15)
            ->where('taskTypes.current_page', 1)
            ->where('taskTypes.last_page', 2)
            ->where('taskTypes.total', 16),
        );

        $secondPage = $this
            ->actingAs($user)
            ->get($this->taskTypesRoute($user, 'setup.task-types.index').'?page=2');

        $secondPage->assertOk();
        $secondPage->assertInertia(fn (Assert $page) => $page
            ->has('taskTypes.data', 1)
            ->where('taskTypes.current_page', 2)
            ->where('taskTypes.total', 16),
        );
    }

    public function test_valid_payload_creates_a_task_type(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskTypesRoute($user, 'setup.task-types.store'), [
                'name' => 'Feature',
                'description' => 'New product capability',
                'status' => TaskTypeStatus::Active->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('taskType.name', 'Feature');
        $response->assertJsonPath('message', 'Task type created.');

        $this->assertDatabaseHas('task_types', [
            'name' => 'Feature',
            'description' => 'New product capability',
            'status' => TaskTypeStatus::Active->value,
            'created_by' => $user->id,
        ]);
    }

    public function test_empty_payload_rejects_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskTypesRoute($user, 'setup.task-types.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'status']);
        $response->assertJsonPath('errors.name.0', 'The name field is required.');
        $response->assertJsonPath('errors.status.0', 'The status field is required.');
        $this->assertDatabaseCount('task_types', 0);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $user = User::factory()->create();
        TaskType::factory()->create(['name' => 'Enhancement']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskTypesRoute($user, 'setup.task-types.store'), [
                'name' => 'Enhancement',
                'status' => TaskTypeStatus::Active->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'The name has already been taken.');
        $this->assertDatabaseCount('task_types', 1);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskTypesRoute($user, 'setup.task-types.store'), [
                'name' => 'Research',
                'status' => 'archived',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.status.0', 'The selected status is invalid.');
        $this->assertDatabaseCount('task_types', 0);
    }

    public function test_valid_payload_updates_a_task_type(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create([
            'name' => 'Legacy',
            'status' => TaskTypeStatus::Active,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->taskTypesRoute($user, 'setup.task-types.update', $taskType), [
                'name' => 'Maintenance',
                'description' => 'Updated description',
                'status' => TaskTypeStatus::Inactive->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('taskType.name', 'Maintenance');
        $response->assertJsonPath('message', 'Task type updated.');

        $this->assertDatabaseHas('task_types', [
            'id' => $taskType->id,
            'name' => 'Maintenance',
            'description' => 'Updated description',
            'status' => TaskTypeStatus::Inactive->value,
            'updated_by' => $user->id,
        ]);
    }

    public function test_a_task_type_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create(['name' => 'Retiring']);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->taskTypesRoute($user, 'setup.task-types.destroy', $taskType));

        $response->assertOk();
        $response->assertJsonPath('message', 'Task type deleted.');

        $this->assertSoftDeleted($taskType);
        $this->assertDatabaseHas('task_types', [
            'id' => $taskType->id,
            'deleted_by' => $user->id,
        ]);
    }

    public function test_soft_deleted_name_can_be_reused(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create(['name' => 'Shared Name']);
        $taskType->delete();

        $response = $this
            ->actingAs($user)
            ->postJson($this->taskTypesRoute($user, 'setup.task-types.store'), [
                'name' => 'Shared Name',
                'status' => TaskTypeStatus::Active->value,
            ]);

        $response->assertCreated();
        $this->assertDatabaseCount('task_types', 2);
    }

    private function taskTypesRoute(User $user, string $name, ?TaskType $taskType = null): string
    {
        return route($name, array_filter([
            'current_team' => $user->currentTeam?->slug,
            'task_type' => $taskType,
        ]));
    }
}

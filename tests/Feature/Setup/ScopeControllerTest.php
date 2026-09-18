<?php

namespace Tests\Feature\Setup;

use App\Enums\ScopeStatus;
use App\Models\Setup\Scope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScopeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->get($this->scopesRoute($user, 'setup.scopes.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_scopes_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $scope = Scope::factory()->create([
            'name' => 'API Integration',
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get($this->scopesRoute($user, 'setup.scopes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('setup/scopes/index')
            ->has('scopes.data', 1)
            ->where('scopes.data.0.id', $scope->id)
            ->where('scopes.data.0.name', 'API Integration')
            ->has('statusOptions', 2),
        );
    }

    public function test_valid_payload_creates_a_scope(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->scopesRoute($user, 'setup.scopes.store'), [
                'name' => 'Web Application',
                'description' => 'Customer-facing web work',
                'status' => ScopeStatus::Active->value,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('scope.name', 'Web Application');
        $response->assertJsonPath('message', 'Scope created.');

        $this->assertDatabaseHas('scopes', [
            'name' => 'Web Application',
            'description' => 'Customer-facing web work',
            'status' => ScopeStatus::Active->value,
            'created_by' => $user->id,
        ]);
    }

    public function test_empty_payload_rejects_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from($this->scopesRoute($user, 'setup.scopes.index'))
            ->postJson($this->scopesRoute($user, 'setup.scopes.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'status']);
        $response->assertJsonPath('errors.name.0', 'The name field is required.');
        $response->assertJsonPath('errors.status.0', 'The status field is required.');
        $this->assertDatabaseCount('scopes', 0);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $user = User::factory()->create();
        Scope::factory()->create(['name' => 'Mobile App']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->scopesRoute($user, 'setup.scopes.store'), [
                'name' => 'Mobile App',
                'status' => ScopeStatus::Active->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'The name has already been taken.');
        $this->assertDatabaseCount('scopes', 1);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->scopesRoute($user, 'setup.scopes.store'), [
                'name' => 'Reporting',
                'status' => 'archived',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.status.0', 'The selected status is invalid.');
        $this->assertDatabaseCount('scopes', 0);
    }

    public function test_valid_payload_updates_a_scope(): void
    {
        $user = User::factory()->create();
        $scope = Scope::factory()->create([
            'name' => 'Legacy',
            'status' => ScopeStatus::Active,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->scopesRoute($user, 'setup.scopes.update', $scope), [
                'name' => 'Modernization',
                'description' => 'Updated description',
                'status' => ScopeStatus::Inactive->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('scope.name', 'Modernization');
        $response->assertJsonPath('message', 'Scope updated.');

        $this->assertDatabaseHas('scopes', [
            'id' => $scope->id,
            'name' => 'Modernization',
            'description' => 'Updated description',
            'status' => ScopeStatus::Inactive->value,
            'updated_by' => $user->id,
        ]);
    }

    public function test_a_scope_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $scope = Scope::factory()->create(['name' => 'Retiring']);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->scopesRoute($user, 'setup.scopes.destroy', $scope));

        $response->assertOk();
        $response->assertJsonPath('message', 'Scope deleted.');

        $this->assertSoftDeleted($scope);
        $this->assertDatabaseHas('scopes', [
            'id' => $scope->id,
            'deleted_by' => $user->id,
        ]);
    }

    public function test_soft_deleted_name_can_be_reused(): void
    {
        $user = User::factory()->create();
        $scope = Scope::factory()->create(['name' => 'Shared Name']);
        $scope->delete();

        $response = $this
            ->actingAs($user)
            ->postJson($this->scopesRoute($user, 'setup.scopes.store'), [
                'name' => 'Shared Name',
                'status' => ScopeStatus::Active->value,
            ]);

        $response->assertCreated();
        $this->assertDatabaseCount('scopes', 2);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function scopesRoute(User $user, string $name, ?Scope $scope = null): string
    {
        return route($name, array_filter([
            'current_team' => $user->currentTeam?->slug,
            'scope' => $scope,
        ]));
    }
}

<?php

namespace Tests\Feature\Setup;

use App\Models\Setup\Label;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LabelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->get($this->labelsRoute($user, 'setup.labels.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_labels_index_page_only_shows_the_current_teams_labels(): void
    {
        $user = User::factory()->create();
        $ownLabel = Label::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Bug']);
        Label::factory()->create(['name' => 'Someone elses label']);

        $response = $this
            ->actingAs($user)
            ->get($this->labelsRoute($user, 'setup.labels.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('setup/labels/index')
            ->has('labels.data', 1)
            ->where('labels.data.0.id', $ownLabel->id),
        );
    }

    public function test_valid_payload_creates_a_label(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->labelsRoute($user, 'setup.labels.store'), [
                'name' => 'Bug',
                'color' => '#ff0000',
                'description' => 'Something is broken',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('label.name', 'Bug');

        $this->assertDatabaseHas('labels', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Bug',
            'color' => '#ff0000',
            'created_by' => $user->id,
        ]);
    }

    public function test_empty_payload_rejects_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->labelsRoute($user, 'setup.labels.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_the_same_label_name_is_allowed_on_different_teams(): void
    {
        $user = User::factory()->create();
        Label::factory()->create(['team_id' => Team::factory(), 'name' => 'Bug']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->labelsRoute($user, 'setup.labels.store'), [
                'name' => 'Bug',
            ]);

        $response->assertCreated();
    }

    public function test_a_duplicate_name_within_the_same_team_is_rejected(): void
    {
        $user = User::factory()->create();
        Label::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Bug']);

        $response = $this
            ->actingAs($user)
            ->postJson($this->labelsRoute($user, 'setup.labels.store'), [
                'name' => 'Bug',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_a_label_can_be_updated(): void
    {
        $user = User::factory()->create();
        $label = Label::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Old name']);

        $response = $this
            ->actingAs($user)
            ->putJson($this->labelsRoute($user, 'setup.labels.update', $label), [
                'name' => 'New name',
            ]);

        $response->assertOk();
        $response->assertJsonPath('label.name', 'New name');
    }

    public function test_a_label_cannot_be_reached_through_another_teams_url(): void
    {
        $user = User::factory()->create();
        $label = Label::factory()->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->labelsRoute($user, 'setup.labels.update', $label), [
                'name' => 'Hijacked',
            ]);

        $response->assertNotFound();
    }

    public function test_a_label_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $label = Label::factory()->create(['team_id' => $user->currentTeam->id]);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->labelsRoute($user, 'setup.labels.destroy', $label));

        $response->assertOk();
        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
    }

    private function labelsRoute(User $user, string $name, ?Label $label = null): string
    {
        return route($name, array_filter([
            'current_team' => $user->currentTeam?->slug,
            'label' => $label,
        ]));
    }
}

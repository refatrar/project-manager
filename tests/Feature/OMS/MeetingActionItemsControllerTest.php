<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\GetOrCreateMeetingActionList;
use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\TodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingActionItemsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attendee_can_add_and_toggle_an_action_item_on_a_team_wide_meeting(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $organizer->currentTeam->members()->attach($attendee, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $organizer->currentTeam->id, 'organized_by' => $organizer->id]);
        $meeting->attendees()->create(['user_id' => $attendee->id, 'role' => 'participant', 'attendance_status' => 'accepted']);
        $list = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $response = $this
            ->actingAs($attendee)
            ->postJson($this->itemRoute($attendee, 'todo-lists.items.store', $list, null, $organizer->currentTeam), [
                'title' => 'Send follow-up email',
                'priority' => 'medium',
            ]);

        $response->assertCreated();
        $item = $response->json('item.id');

        $toggleResponse = $this
            ->actingAs($attendee)
            ->patchJson($this->itemRoute($attendee, 'todo-lists.items.toggle', $list, $item, $organizer->currentTeam), [
                'is_completed' => true,
            ]);

        $toggleResponse->assertOk();
        $this->assertDatabaseHas('todo_items', ['id' => $item, 'is_completed' => true]);
    }

    public function test_a_non_member_cannot_manage_a_project_meetings_action_list(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->currentTeam->members()->attach($outsider, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);
        $list = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $response = $this
            ->actingAs($outsider)
            ->postJson($this->itemRoute($outsider, 'todo-lists.items.store', $list, null, $owner->currentTeam), [
                'title' => 'Sneaky item',
                'priority' => 'medium',
            ]);

        $response->assertForbidden();
    }

    public function test_a_team_admin_can_manage_any_meetings_action_list_without_being_an_attendee(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $meeting = Meeting::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $owner->id,
        ]);
        $list = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $response = $this
            ->actingAs($admin)
            ->postJson($this->itemRoute($admin, 'todo-lists.items.store', $list, null, $owner->currentTeam), [
                'title' => 'Admin-added item',
                'priority' => 'medium',
            ]);

        $response->assertCreated();
    }

    private function itemRoute(User $user, string $name, TodoList $list, ?int $item = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'todo_list' => $list,
            'item' => $item,
        ]));
    }
}

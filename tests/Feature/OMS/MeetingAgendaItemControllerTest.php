<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\Meeting;
use App\Models\OMS\MeetingAgendaItem;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingAgendaItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_can_be_added_and_lands_at_the_end_of_the_order(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        MeetingAgendaItem::factory()->for($meeting)->create(['position' => 3]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->agendaRoute($user, 'meetings.agenda-items.store', $meeting), [
                'title' => 'Discuss roadmap',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('agendaItem.title', 'Discuss roadmap');
        $response->assertJsonPath('agendaItem.position', 4);
    }

    public function test_an_item_can_link_a_task_from_the_meetings_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $user->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->agendaRoute($user, 'meetings.agenda-items.store', $meeting), [
                'title' => 'Review PR status',
                'task_id' => $task->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('agendaItem.task.id', $task->id);
    }

    public function test_a_task_from_another_project_cannot_be_linked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $otherProject = Project::factory()->for($user->currentTeam)->create();
        $foreignTask = Task::factory()->for($otherProject)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $user->currentTeam->id,
            'project_id' => $project->id,
            'organized_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->agendaRoute($user, 'meetings.agenda-items.store', $meeting), [
                'title' => 'Review PR status',
                'task_id' => $foreignTask->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['task_id']);
    }

    public function test_a_task_cannot_be_linked_on_a_team_wide_meeting(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $meeting = Meeting::factory()->create([
            'team_id' => $user->currentTeam->id,
            'project_id' => null,
            'organized_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson($this->agendaRoute($user, 'meetings.agenda-items.store', $meeting), [
                'title' => 'Review PR status',
                'task_id' => $task->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['task_id']);
    }

    public function test_an_item_can_be_updated(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $item = MeetingAgendaItem::factory()->for($meeting)->create();

        $response = $this
            ->actingAs($user)
            ->putJson($this->agendaRoute($user, 'meetings.agenda-items.update', $meeting, $item), [
                'title' => 'Renamed item',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meeting_agenda_items', ['id' => $item->id, 'title' => 'Renamed item']);
    }

    public function test_an_item_can_be_moved_to_a_new_position(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $item = MeetingAgendaItem::factory()->for($meeting)->create(['position' => 0]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->agendaRoute($user, 'meetings.agenda-items.move', $meeting, $item), [
                'position' => 5,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meeting_agenda_items', ['id' => $item->id, 'position' => 5]);
    }

    public function test_an_item_can_be_toggled_discussed(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $item = MeetingAgendaItem::factory()->for($meeting)->create(['is_discussed' => false]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->agendaRoute($user, 'meetings.agenda-items.toggle', $meeting, $item), [
                'is_discussed' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('meeting_agenda_items', ['id' => $item->id, 'is_discussed' => true]);
    }

    public function test_an_item_can_be_removed(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $item = MeetingAgendaItem::factory()->for($meeting)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->agendaRoute($user, 'meetings.agenda-items.destroy', $meeting, $item));

        $response->assertOk();
        $this->assertDatabaseMissing('meeting_agenda_items', ['id' => $item->id]);
    }

    public function test_a_non_organizer_participant_cannot_add_an_agenda_item(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $owner->currentTeam->members()->attach($participant, ['role' => TeamRole::Member->value]);
        $meeting = Meeting::factory()->create(['team_id' => $owner->currentTeam->id, 'organized_by' => $owner->id]);

        $response = $this
            ->actingAs($participant)
            ->postJson($this->agendaRoute($participant, 'meetings.agenda-items.store', $meeting, null, $owner->currentTeam), [
                'title' => 'Sneaky item',
            ]);

        $response->assertForbidden();
    }

    public function test_an_item_from_another_meeting_cannot_be_reached_through_this_meetings_url(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $otherMeeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id, 'organized_by' => $user->id]);
        $item = MeetingAgendaItem::factory()->for($otherMeeting)->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->agendaRoute($user, 'meetings.agenda-items.destroy', $meeting, $item));

        $response->assertNotFound();
    }

    private function agendaRoute(User $user, string $name, Meeting $meeting, ?MeetingAgendaItem $item = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)?->slug,
            'meeting' => $meeting,
            'item' => $item,
        ]));
    }
}

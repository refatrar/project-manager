<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\GetOrCreateMeetingActionList;
use App\Enums\TodoListType;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetOrCreateMeetingActionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_action_list_is_created_the_first_time_it_is_requested(): void
    {
        $meeting = Meeting::factory()->create();

        $list = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $this->assertSame(TodoListType::MeetingActions, $list->type);
        $this->assertSame($meeting->id, $list->meeting_id);
        $this->assertSame($meeting->team_id, $list->team_id);
        $this->assertNull($list->owner_id);
    }

    public function test_requesting_it_again_returns_the_same_list(): void
    {
        $meeting = Meeting::factory()->create();

        $first = app(GetOrCreateMeetingActionList::class)->handle($meeting);
        $second = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('todo_lists', 1);
    }

    public function test_a_team_wide_meeting_with_no_project_still_gets_a_list(): void
    {
        $project = Project::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $project->team_id, 'project_id' => null]);

        $list = app(GetOrCreateMeetingActionList::class)->handle($meeting);

        $this->assertNull($list->project_id);
    }
}

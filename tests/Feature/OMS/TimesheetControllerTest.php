<?php

namespace Tests\Feature\OMS;

use App\Enums\ApprovalStatus;
use App\Models\OMS\Project;
use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_week_grid_groups_entries_by_project_and_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);
        TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'project_id' => $project->id,
            'activity_type' => 'development',
            'logged_on' => '2026-03-10',
            'started_at' => '2026-03-10 09:00:00',
            'ended_at' => '2026-03-10 13:00:00',
            'duration_minutes' => 240,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('timesheet.index', ['current_team' => $user->currentTeam->slug, 'week' => '2026-03-09']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('timesheet/index')
            ->where('weekStart', '2026-03-09')
            ->where('weekEnd', '2026-03-15')
            ->where('weekTotal', 4)
            ->where('rows.0.total', 4)
            ->where('rows.0.days.2026-03-10', 4)
            ->where('canSubmit', true));
    }

    public function test_submitting_the_week_marks_pending_entries_submitted(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'logged_on' => '2026-03-10',
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('timesheet.submit', ['current_team' => $user->currentTeam->slug]), ['week' => '2026-03-09']);

        $response->assertOk();
        $this->assertDatabaseHas('time_logs', ['id' => $timeLog->id, 'approval_status' => ApprovalStatus::Submitted->value]);
    }

    public function test_a_user_only_sees_their_own_entries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->currentTeam->members()->attach($other, ['role' => 'member']);
        TimeLog::factory()->create([
            'user_id' => $other->id,
            'team_id' => $owner->currentTeam->id,
            'logged_on' => '2026-03-10',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('timesheet.index', ['current_team' => $owner->currentTeam->slug, 'week' => '2026-03-09']));

        $response->assertInertia(fn ($page) => $page
            ->component('timesheet/index')
            ->where('weekTotal', 0)
            ->has('rows', 0));
    }
}

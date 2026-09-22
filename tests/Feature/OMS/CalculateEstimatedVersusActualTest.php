<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\CalculateEstimatedVersusActual;
use App\Enums\ApprovalStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateEstimatedVersusActualTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sums_booked_hours_and_logged_hours_per_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);

        // 5 days (inclusive) at 4h/day = 20h booked.
        ResourceAllocation::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-13',
            'hours_per_day' => 4,
        ]);

        TimeLog::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'duration_minutes' => 600, // 10h
            'approval_status' => ApprovalStatus::Approved,
        ]);
        TimeLog::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'duration_minutes' => 300, // 5h more
            'approval_status' => ApprovalStatus::Pending,
        ]);
        TimeLog::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'duration_minutes' => 999, // excluded
            'approval_status' => ApprovalStatus::Rejected,
        ]);

        $rows = app(CalculateEstimatedVersusActual::class)->handle($project);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($member->id, $row['user']['id']);
        $this->assertSame(20.0, $row['estimated_hours']);
        $this->assertSame(15.0, $row['actual_hours']);
        $this->assertSame(-5.0, $row['variance_hours']);
    }

    public function test_a_member_with_only_logged_time_and_no_booking_still_appears(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);

        TimeLog::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'duration_minutes' => 120,
            'approval_status' => ApprovalStatus::Approved,
        ]);

        $rows = app(CalculateEstimatedVersusActual::class)->handle($project);

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows->first()['estimated_hours']);
        $this->assertSame(2.0, $rows->first()['actual_hours']);
    }

    public function test_the_project_workspace_exposes_the_report(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        ResourceAllocation::factory()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 3,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('projects.show', ['current_team' => $owner->currentTeam->slug, 'project' => $project]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/show')
            ->where('estimatedVsActual.0.user.id', $owner->id)
            ->where('estimatedVsActual.0.estimated_hours', 3));
    }
}

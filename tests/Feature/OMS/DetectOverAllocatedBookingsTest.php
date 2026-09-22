<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\CalculateUserAvailability;
use App\Actions\OMS\DetectOverAllocatedBookings;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectOverAllocatedBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_bookings_across_different_projects_that_together_exceed_capacity_are_both_flagged(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        UserWorkSchedule::factory()->create([
            'user_id' => $member->id,
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $projectA = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $projectB = Project::factory()->create(['team_id' => $owner->currentTeam->id]);

        // 2026-03-09 is a Monday. 5h + 5h = 10h against an 8h capacity.
        $bookingA = ResourceAllocation::factory()->create([
            'user_id' => $member->id,
            'project_id' => $projectA->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 5,
        ]);
        $bookingB = ResourceAllocation::factory()->create([
            'user_id' => $member->id,
            'project_id' => $projectB->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 5,
        ]);

        $allocations = ResourceAllocation::query()->with('user')->whereIn('id', [$bookingA->id, $bookingB->id])->get();

        $flags = app(DetectOverAllocatedBookings::class)->handle($allocations, app(CalculateUserAvailability::class));

        $this->assertTrue($flags[$bookingA->id]);
        $this->assertTrue($flags[$bookingB->id]);
    }

    public function test_a_booking_within_capacity_is_not_flagged(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        UserWorkSchedule::factory()->create([
            'user_id' => $member->id,
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);

        $booking = ResourceAllocation::factory()->create([
            'user_id' => $member->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 4,
        ]);

        $allocations = ResourceAllocation::query()->with('user')->whereIn('id', [$booking->id])->get();

        $flags = app(DetectOverAllocatedBookings::class)->handle($allocations, app(CalculateUserAvailability::class));

        $this->assertFalse($flags[$booking->id]);
    }

    public function test_the_project_workspace_surfaces_the_flag_on_each_booking(): void
    {
        $owner = User::factory()->create();
        UserWorkSchedule::factory()->create([
            'user_id' => $owner->id,
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        ResourceAllocation::factory()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 10,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('projects.show', ['current_team' => $owner->currentTeam->slug, 'project' => $project]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/show')
            ->where('resourceAllocations.0.over_allocated', true));
    }
}

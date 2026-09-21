<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\DetermineProjectHealth;
use App\Data\ProjectProgressData;
use App\Enums\ProjectHealth;
use App\Models\OMS\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetermineProjectHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_with_no_end_date_is_always_on_track(): void
    {
        $project = Project::factory()->create(['end_date' => null]);

        $health = app(DetermineProjectHealth::class)->handle($project, $this->progress(0));

        $this->assertSame(ProjectHealth::OnTrack, $health);
    }

    public function test_a_project_past_its_end_date_and_unfinished_is_off_track(): void
    {
        $project = Project::factory()->create([
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDay(),
        ]);

        $health = app(DetermineProjectHealth::class)->handle($project, $this->progress(80));

        $this->assertSame(ProjectHealth::OffTrack, $health);
    }

    public function test_a_project_finished_exactly_on_schedule_is_on_track(): void
    {
        $project = Project::factory()->create([
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
        ]);

        // Halfway through the schedule with roughly half the work done.
        $health = app(DetermineProjectHealth::class)->handle($project, $this->progress(50));

        $this->assertSame(ProjectHealth::OnTrack, $health);
    }

    public function test_a_project_significantly_behind_schedule_is_at_risk(): void
    {
        $project = Project::factory()->create([
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
        ]);

        // Halfway through the schedule (expected ~50%) but only 20% done: 30pt gap.
        $health = app(DetermineProjectHealth::class)->handle($project, $this->progress(20));

        $this->assertSame(ProjectHealth::AtRisk, $health);
    }

    public function test_a_project_severely_behind_schedule_is_off_track(): void
    {
        $project = Project::factory()->create([
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
        ]);

        // Halfway through the schedule (expected ~50%) but 0% done: 50pt gap.
        $health = app(DetermineProjectHealth::class)->handle($project, $this->progress(0));

        $this->assertSame(ProjectHealth::OffTrack, $health);
    }

    public function test_hours_significantly_over_estimate_while_unfinished_is_at_risk(): void
    {
        $project = Project::factory()->create(['end_date' => null]);

        $health = app(DetermineProjectHealth::class)->handle(
            $project,
            $this->progress(50, estimatedHours: '10', loggedHours: '15'),
        );

        $this->assertSame(ProjectHealth::AtRisk, $health);
    }

    public function test_hours_over_estimate_on_a_finished_project_does_not_downgrade_health(): void
    {
        $project = Project::factory()->create(['end_date' => null]);

        $health = app(DetermineProjectHealth::class)->handle(
            $project,
            $this->progress(100, estimatedHours: '10', loggedHours: '20'),
        );

        $this->assertSame(ProjectHealth::OnTrack, $health);
    }

    private function progress(int $percentage, string $estimatedHours = '0', string $loggedHours = '0'): ProjectProgressData
    {
        return new ProjectProgressData(
            totalTasks: 0,
            completedTasks: 0,
            inProgressTasks: 0,
            blockedTasks: 0,
            overdueTasks: 0,
            estimatedHours: $estimatedHours,
            loggedHours: $loggedHours,
            remainingHours: '0',
            progressPercentage: $percentage,
        );
    }
}

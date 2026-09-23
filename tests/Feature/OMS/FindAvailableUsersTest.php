<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\FindAvailableUsers;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\WorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FindAvailableUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_enough_free_hours_every_working_day_is_included(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        // Monday 2026-03-09
        $found = app(FindAvailableUsers::class)->handle(
            new Collection([$user]),
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-09'),
            4.0,
        );

        $this->assertTrue($found->contains($user));
    }

    public function test_a_user_without_enough_free_hours_on_any_working_day_is_excluded(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        $project = Project::factory()->create();
        ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 6,
        ]);

        $found = app(FindAvailableUsers::class)->handle(
            new Collection([$user]),
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-09'),
            4.0,
        );

        $this->assertFalse($found->contains($user));
    }

    public function test_non_working_days_in_the_range_do_not_disqualify_a_user(): void
    {
        $user = User::factory()->create();
        // Only works Monday.
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        // Monday 2026-03-09 through Sunday 2026-03-15.
        $found = app(FindAvailableUsers::class)->handle(
            new Collection([$user]),
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-15'),
            4.0,
        );

        $this->assertTrue($found->contains($user));
    }

    public function test_a_user_with_no_working_days_in_the_range_is_excluded(): void
    {
        $user = User::factory()->create();

        $found = app(FindAvailableUsers::class)->handle(
            new Collection([$user]),
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-09'),
            4.0,
        );

        $this->assertFalse($found->contains($user));
    }
}

<?php

namespace Tests\Feature\OMS;

use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResourceAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reserving_between_scope_only_returns_bookings_that_overlap_the_range(): void
    {
        $user = User::factory()->create();

        $overlapping = ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-13',
        ]);

        ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-03',
        ]);

        $found = ResourceAllocation::reservingBetween(
            Carbon::parse('2026-03-11'),
            Carbon::parse('2026-03-15'),
        )->pluck('id')->all();

        $this->assertSame([$overlapping->id], $found);
    }

    public function test_cancelled_bookings_do_not_reserve_capacity(): void
    {
        ResourceAllocation::factory()->cancelled()->create([
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-13',
        ]);

        $found = ResourceAllocation::reservingBetween(
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-13'),
        )->count();

        $this->assertSame(0, $found);
    }

    public function test_only_approved_time_off_reduces_availability(): void
    {
        $user = User::factory()->create();

        $approved = TimeOffRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-10',
            'ends_on' => '2026-03-11',
        ]);

        TimeOffRequest::factory()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-10',
            'ends_on' => '2026-03-11',
        ]);

        $found = TimeOffRequest::approvedBetween(
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-12'),
        )->pluck('id')->all();

        $this->assertSame([$approved->id], $found);
    }

    public function test_the_effective_on_scope_ignores_schedules_that_have_expired(): void
    {
        $user = User::factory()->create();

        $current = UserWorkSchedule::factory()->create([
            'user_id' => $user->id,
            'day_of_week' => 1,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
        ]);

        UserWorkSchedule::factory()->create([
            'user_id' => $user->id,
            'day_of_week' => 2,
            'effective_from' => '2025-01-01',
            'effective_until' => '2025-12-31',
        ]);

        $found = UserWorkSchedule::effectiveOn(Carbon::parse('2026-03-10'))->pluck('id')->all();

        $this->assertSame([$current->id], $found);
    }

    public function test_a_users_occupied_hours_combine_bookings_across_projects(): void
    {
        $user = User::factory()->create();

        ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-13',
            'hours_per_day' => 5,
        ]);

        ResourceAllocation::factory()->confirmed()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-13',
            'hours_per_day' => 3,
        ]);

        $occupiedHours = $user->resourceAllocations()
            ->reservingBetween(Carbon::parse('2026-03-09'), Carbon::parse('2026-03-13'))
            ->sum('hours_per_day');

        $this->assertEquals(8, $occupiedHours);
    }
}

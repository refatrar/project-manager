<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\CalculateUserAvailability;
use App\Models\OMS\Holiday;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\WorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalculateUserAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_day_with_no_schedule_has_zero_capacity(): void
    {
        $user = User::factory()->create();

        $days = app(CalculateUserAvailability::class)->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'));

        $this->assertSame(0.0, $days->first()->capacityHours);
        $this->assertSame(0.0, $days->first()->availableHours);
    }

    public function test_capacity_comes_from_the_schedule_version_in_force_that_day(): void
    {
        $user = User::factory()->create();
        // Monday, 2026-03-09
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'is_working_day' => true,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(8.0, $day->capacityHours);
        $this->assertSame(8.0, $day->availableHours);
    }

    public function test_occupied_hours_are_subtracted_from_capacity(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'is_working_day' => true,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        $project = Project::factory()->create();
        ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 5,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(8.0, $day->capacityHours);
        $this->assertSame(5.0, $day->occupiedHours);
        $this->assertSame(3.0, $day->availableHours);
    }

    public function test_cancelled_allocations_do_not_occupy_capacity(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        $project = Project::factory()->create();
        ResourceAllocation::factory()->cancelled()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 5,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(0.0, $day->occupiedHours);
        $this->assertSame(8.0, $day->availableHours);
    }

    public function test_an_approved_full_day_time_off_zeroes_out_availability(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        TimeOffRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'is_full_day' => true,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(8.0, $day->unavailableHours);
        $this->assertSame(0.0, $day->availableHours);
    }

    public function test_pending_time_off_does_not_reduce_availability(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        TimeOffRequest::factory()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'is_full_day' => true,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(0.0, $day->unavailableHours);
        $this->assertSame(8.0, $day->availableHours);
    }

    public function test_a_partial_day_time_off_subtracts_only_its_own_hours(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        TimeOffRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'is_full_day' => false,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'total_hours' => 4,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(4.0, $day->unavailableHours);
        $this->assertSame(4.0, $day->availableHours);
    }

    public function test_availability_never_goes_negative(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 4,
            'effective_from' => '2026-01-01',
        ]);
        $project = Project::factory()->create();
        ResourceAllocation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-09',
            'hours_per_day' => 8,
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(0.0, $day->availableHours);
    }

    public function test_it_returns_one_entry_per_day_in_the_range(): void
    {
        $user = User::factory()->create();

        $days = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-15'));

        $this->assertCount(7, $days);
        $this->assertSame('2026-03-09', $days->first()->date);
        $this->assertSame('2026-03-15', $days->last()->date);
    }

    public function test_a_non_working_day_has_zero_capacity_even_with_a_schedule_row(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->nonWorkingDay()->create([
            'day_of_week' => 1,
            'effective_from' => '2026-01-01',
        ]);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(0.0, $day->capacityHours);
    }

    public function test_a_holiday_zeroes_capacity_even_on_an_otherwise_working_day(): void
    {
        $user = User::factory()->create();
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'is_working_day' => true,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);
        Holiday::factory()->create(['date' => '2026-03-09']);

        $day = app(CalculateUserAvailability::class)
            ->handle($user, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-09'))
            ->first();

        $this->assertSame(0.0, $day->capacityHours);
        $this->assertSame(0.0, $day->availableHours);
    }
}

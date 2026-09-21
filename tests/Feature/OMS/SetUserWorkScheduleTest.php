<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\SetUserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SetUserWorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_one_row_per_day(): void
    {
        $user = User::factory()->create();

        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-01-01'), $this->weekOf());

        $this->assertSame(7, $user->workSchedules()->count());
        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'day_of_week' => 6,
            'is_working_day' => false,
            'capacity_hours' => 0,
            'start_time' => null,
        ]);
    }

    public function test_a_non_working_day_is_normalized_regardless_of_submitted_values(): void
    {
        $user = User::factory()->create();
        $days = $this->weekOf();
        // A client that submits stray times/capacity for a day it also
        // marked as non-working must not have those values persisted.
        $days[5]['start_time'] = '09:00';
        $days[5]['capacity_hours'] = 8;

        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-01-01'), $days);

        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'day_of_week' => 6,
            'start_time' => null,
            'capacity_hours' => 0,
        ]);
    }

    public function test_starting_a_new_version_closes_the_previously_open_one(): void
    {
        $user = User::factory()->create();
        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-01-01'), $this->weekOf());

        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-06-01'), $this->weekOf());

        $this->assertSame(14, $user->workSchedules()->count());
        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-05-31',
        ]);
        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'effective_from' => '2026-06-01',
            'effective_until' => null,
        ]);
    }

    public function test_resubmitting_the_same_effective_date_replaces_it_in_place(): void
    {
        $user = User::factory()->create();
        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-01-01'), $this->weekOf());

        $days = $this->weekOf();
        $days[0]['capacity_hours'] = 6;
        app(SetUserWorkSchedule::class)->handle($user, $user->currentTeam, Carbon::parse('2026-01-01'), $days);

        $this->assertSame(7, $user->workSchedules()->count());
        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'day_of_week' => 1,
            'capacity_hours' => 6,
            'effective_until' => null,
        ]);
    }

    /**
     * @return array<int, array{day_of_week: int, is_working_day: bool, start_time: ?string, end_time: ?string, break_minutes: int, capacity_hours: float}>
     */
    private function weekOf(): array
    {
        return array_map(fn (int $day): array => [
            'day_of_week' => $day,
            'is_working_day' => $day <= 5,
            'start_time' => $day <= 5 ? '09:00' : null,
            'end_time' => $day <= 5 ? '18:00' : null,
            'break_minutes' => $day <= 5 ? 60 : 0,
            'capacity_hours' => $day <= 5 ? 8 : 0,
        ], range(1, 7));
    }
}

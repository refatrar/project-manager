<?php

namespace Tests\Feature\OMS;

use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserWorkScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_groups_rows_into_versions(): void
    {
        $user = User::factory()->create();
        UserWorkSchedule::factory()->create([
            'user_id' => $user->id,
            'day_of_week' => 1,
            'effective_from' => '2026-01-01',
        ]);
        UserWorkSchedule::factory()->create([
            'user_id' => $user->id,
            'day_of_week' => 2,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('work-schedule.index', ['current_team' => $user->currentTeam->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('work-schedule/index')
            ->has('versions', 1)
            ->where('versions.0.effective_from', '2026-01-01')
            ->has('versions.0.days', 2));
    }

    public function test_a_user_can_save_a_new_weekly_schedule(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('work-schedule.store', ['current_team' => $user->currentTeam->slug]), [
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertCreated();
        $this->assertSame(7, $user->workSchedules()->count());
    }

    public function test_it_rejects_a_working_day_with_no_start_time(): void
    {
        $user = User::factory()->create();
        $days = $this->weekOf();
        $days[0]['start_time'] = null;

        $response = $this
            ->actingAs($user)
            ->postJson(route('work-schedule.store', ['current_team' => $user->currentTeam->slug]), [
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $days,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['days.0.start_time']);
    }

    public function test_it_rejects_a_backdated_effective_from(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('work-schedule.store', ['current_team' => $user->currentTeam->slug]), [
                'effective_from' => Carbon::yesterday()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['effective_from']);
    }

    public function test_a_user_cannot_see_another_users_schedule(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        UserWorkSchedule::factory()->create(['user_id' => $other->id, 'day_of_week' => 1]);

        $response = $this
            ->actingAs($user)
            ->get(route('work-schedule.index', ['current_team' => $user->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page->has('versions', 0));
    }

    /**
     * @return array<int, array<string, mixed>>
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

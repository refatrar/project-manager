<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\OMS\WorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermission(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_the_index_page_groups_the_schedules_rows_into_versions(): void
    {
        $admin = $this->adminWithPermission();
        WorkSchedule::factory()->create(['day_of_week' => 1, 'effective_from' => '2026-01-01']);
        WorkSchedule::factory()->create(['day_of_week' => 2, 'effective_from' => '2026-01-01']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.work-schedules.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/work-schedules/index')
            ->has('versions', 1)
            ->where('versions.0.effective_from', '2026-01-01')
            ->has('versions.0.days', 2));
    }

    public function test_an_admin_can_save_the_global_weekly_schedule(): void
    {
        $admin = $this->adminWithPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertCreated();
        $this->assertSame(7, WorkSchedule::query()->count());
        $this->assertDatabaseHas('work_schedules', [
            'day_of_week' => 1,
            'capacity_hours' => 8,
        ]);
    }

    public function test_it_rejects_a_working_day_with_no_start_time(): void
    {
        $admin = $this->adminWithPermission();
        $days = $this->weekOf();
        $days[0]['start_time'] = null;

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $days,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['days.0.start_time']);
    }

    public function test_it_rejects_a_backdated_effective_from(): void
    {
        $admin = $this->adminWithPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'effective_from' => Carbon::yesterday()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['effective_from']);
    }

    public function test_a_regular_user_cannot_open_the_admin_work_schedules_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('admin.work-schedules.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_the_team_work_schedule_page_is_no_longer_available(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/'.$user->currentTeam->slug.'/work-schedule');

        $response->assertNotFound();
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

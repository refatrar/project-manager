<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\OMS\UserWorkSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermission(): Admin
    {
        $permission = Permission::factory()->create(['key' => AdminPermission::ManageWorkSchedules->value]);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);

        $admin = Admin::factory()->create();
        $admin->roles()->attach($role);

        return $admin;
    }

    public function test_the_index_page_groups_the_selected_users_rows_into_versions(): void
    {
        $admin = $this->adminWithPermission();
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
            ->actingAs($admin, 'admin')
            ->get(route('admin.work-schedules.index', ['user' => $user->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/work-schedules/index')
            ->where('selectedUserId', $user->id)
            ->has('versions', 1)
            ->where('versions.0.effective_from', '2026-01-01')
            ->has('versions.0.days', 2)
            ->has('availability', 14));
    }

    public function test_an_admin_can_save_a_weekly_schedule_for_a_user(): void
    {
        $admin = $this->adminWithPermission();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'user_id' => $user->id,
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertCreated();
        $this->assertSame(7, $user->workSchedules()->count());
        $this->assertDatabaseHas('user_work_schedules', [
            'user_id' => $user->id,
            'team_id' => $user->current_team_id,
            'day_of_week' => 1,
        ]);
    }

    public function test_it_rejects_a_working_day_with_no_start_time(): void
    {
        $admin = $this->adminWithPermission();
        $user = User::factory()->create();
        $days = $this->weekOf();
        $days[0]['start_time'] = null;

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'user_id' => $user->id,
                'effective_from' => Carbon::today()->toDateString(),
                'days' => $days,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['days.0.start_time']);
    }

    public function test_it_rejects_a_backdated_effective_from(): void
    {
        $admin = $this->adminWithPermission();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.work-schedules.store'), [
                'user_id' => $user->id,
                'effective_from' => Carbon::yesterday()->toDateString(),
                'days' => $this->weekOf(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['effective_from']);
    }

    public function test_selecting_a_user_does_not_include_another_users_schedule(): void
    {
        $admin = $this->adminWithPermission();
        $user = User::factory()->create();
        $other = User::factory()->create();
        UserWorkSchedule::factory()->create(['user_id' => $other->id, 'day_of_week' => 1]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.work-schedules.index', ['user' => $user->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('selectedUserId', $user->id)
            ->has('versions', 0)
            ->has('users', 2));
    }

    public function test_an_admin_without_permission_cannot_open_work_schedules(): void
    {
        $admin = Admin::factory()->create();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.work-schedules.index'));

        $response->assertForbidden();
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

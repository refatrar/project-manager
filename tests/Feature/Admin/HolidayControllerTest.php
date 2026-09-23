<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\OMS\Holiday;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermission(): Admin
    {
        $permission = Permission::factory()->create(['name' => AdminPermission::ManageWorkSchedules->value, 'guard_name' => 'admin']);
        $role = Role::factory()->create();
        $role->givePermissionTo($permission);

        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_an_admin_can_create_a_holiday(): void
    {
        $admin = $this->adminWithPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.holidays.store'), [
                'name' => 'New Year\'s Day',
                'date' => '2027-01-01',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('holidays', [
            'name' => 'New Year\'s Day',
            'date' => '2027-01-01',
            'created_by' => $admin->id,
        ]);
    }

    public function test_the_index_page_lists_holidays_for_the_given_year(): void
    {
        $admin = $this->adminWithPermission();
        Holiday::factory()->create(['date' => '2027-01-01']);
        Holiday::factory()->create(['date' => '2028-01-01']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.holidays.index', ['year' => 2027]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/holidays/index')
            ->where('year', 2027)
            ->has('holidays', 1));
    }

    public function test_a_duplicate_date_fails_validation(): void
    {
        $admin = $this->adminWithPermission();
        Holiday::factory()->create(['date' => '2027-01-01']);

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(route('admin.holidays.store'), [
                'name' => 'Another holiday',
                'date' => '2027-01-01',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_an_admin_can_delete_a_holiday(): void
    {
        $admin = $this->adminWithPermission();
        $holiday = Holiday::factory()->create();

        $response = $this
            ->actingAs($admin, 'admin')
            ->deleteJson(route('admin.holidays.destroy', $holiday));

        $response->assertOk();
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_an_admin_without_permission_cannot_manage_holidays(): void
    {
        $admin = Admin::factory()->create();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.holidays.index'));

        $response->assertForbidden();
    }

    public function test_a_regular_user_cannot_access_the_admin_holidays_panel(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.holidays.index'));

        $response->assertRedirect(route('admin.login'));
    }
}

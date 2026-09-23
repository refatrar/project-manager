<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithManageRoles(): Admin
    {
        $permission = Permission::factory()->create(['name' => AdminPermission::ManageRoles->value, 'guard_name' => 'admin']);
        $role = Role::factory()->create();
        $role->givePermissionTo($permission);

        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_an_admin_with_permission_can_create_a_custom_permission(): void
    {
        $admin = $this->adminWithManageRoles();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.permissions.store'), [
            'name' => 'reports.export',
            'module' => 'Reports',
            'label' => 'Export reports',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('permissions', [
            'name' => 'reports.export',
            'guard_name' => 'admin',
            'module' => 'Reports',
            'label' => 'Export reports',
        ]);
    }

    public function test_an_admin_without_permission_cannot_create_a_custom_permission(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.permissions.store'), [
            'name' => 'reports.export',
            'module' => 'Reports',
            'label' => 'Export reports',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('permissions', ['name' => 'reports.export']);
    }

    public function test_an_admin_can_update_a_custom_permission(): void
    {
        $admin = $this->adminWithManageRoles();
        $permission = Permission::factory()->create(['name' => 'reports.export', 'guard_name' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.permissions.update', $permission), [
            'module' => 'Analytics',
            'label' => 'Export analytics',
        ]);

        $response->assertOk();
        $this->assertSame('Analytics', $permission->refresh()->module);
        $this->assertSame('Export analytics', $permission->refresh()->label);
    }

    public function test_an_admin_can_delete_a_custom_permission(): void
    {
        $admin = $this->adminWithManageRoles();
        $permission = Permission::factory()->create(['name' => 'reports.export', 'guard_name' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.permissions.destroy', $permission));

        $response->assertOk();
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_a_built_in_permission_cannot_be_updated(): void
    {
        $admin = $this->adminWithManageRoles();
        $permission = Permission::query()->where('name', AdminPermission::ManageRoles->value)->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.permissions.update', $permission), [
            'module' => 'Changed',
            'label' => 'Changed',
        ]);

        $response->assertForbidden();
    }

    public function test_a_built_in_permission_cannot_be_deleted(): void
    {
        $admin = $this->adminWithManageRoles();
        $permission = Permission::query()->where('name', AdminPermission::ManageRoles->value)->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.permissions.destroy', $permission));

        $response->assertForbidden();
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_a_regular_user_cannot_access_the_admin_permissions_routes(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('admin.permissions.store'), [
            'name' => 'reports.export',
            'module' => 'Reports',
            'label' => 'Export reports',
        ]);

        $response->assertRedirect(route('admin.login'));
    }
}

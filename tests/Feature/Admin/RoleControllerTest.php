<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_an_admin_with_permission_can_create_a_role_with_permissions(): void
    {
        $admin = $this->adminWithManageRoles();
        $permission = Permission::factory()->create(['name' => 'widgets.manage', 'guard_name' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.roles.store'), [
            'name' => 'Widget Manager',
            'description' => 'Manages widgets.',
            'permissions' => [$permission->id],
        ]);

        $response->assertOk();
        $role = Role::query()->where('name', 'Widget Manager')->firstOrFail();
        $this->assertSame('widget-manager', $role->slug);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->permissions->contains($permission));
    }

    public function test_an_admin_without_permission_cannot_create_a_role(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.roles.store'), [
            'name' => 'Should Not Exist',
            'permissions' => [],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Should Not Exist']);
    }

    public function test_an_admin_can_update_a_roles_name_description_and_permissions(): void
    {
        $admin = $this->adminWithManageRoles();
        $role = Role::factory()->create(['name' => 'Old Name']);
        $oldPermission = Permission::factory()->create(['name' => 'old.manage', 'guard_name' => 'admin']);
        $newPermission = Permission::factory()->create(['name' => 'new.manage', 'guard_name' => 'admin']);
        $role->givePermissionTo($oldPermission);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.roles.update', $role), [
            'name' => 'New Name',
            'description' => 'Updated.',
            'permissions' => [$newPermission->id],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertSame('New Name', $role->name);
        $this->assertSame('Updated.', $role->description);
        $this->assertTrue($role->permissions->contains($newPermission));
        $this->assertFalse($role->permissions->contains($oldPermission));
    }

    public function test_updating_a_system_roles_permissions_is_ignored_but_name_still_updates(): void
    {
        $admin = $this->adminWithManageRoles();
        $systemPermission = Permission::factory()->create(['name' => 'system.manage', 'guard_name' => 'admin']);
        $otherPermission = Permission::factory()->create(['name' => 'other.manage', 'guard_name' => 'admin']);
        $role = Role::factory()->create(['name' => 'Super Admin', 'is_system' => true]);
        $role->givePermissionTo($systemPermission);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.roles.update', $role), [
            'name' => 'Super Admin Renamed',
            'permissions' => [$otherPermission->id],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertSame('Super Admin Renamed', $role->name);
        $this->assertTrue($role->permissions->contains($systemPermission));
        $this->assertFalse($role->permissions->contains($otherPermission));
    }

    public function test_an_admin_can_delete_a_non_system_role(): void
    {
        $admin = $this->adminWithManageRoles();
        $role = Role::factory()->create(['is_system' => false]);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $admin = $this->adminWithManageRoles();
        $role = Role::factory()->create(['is_system' => true]);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.roles.destroy', $role));

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_a_regular_user_cannot_access_the_admin_roles_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.roles.index'));

        $response->assertRedirect(route('admin.login'));
    }
}

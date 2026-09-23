<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithManageAdmins(): Admin
    {
        $permission = Permission::factory()->create(['name' => AdminPermission::ManageAdmins->value, 'guard_name' => 'admin']);
        $role = Role::factory()->create();
        $role->givePermissionTo($permission);

        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_an_admin_with_permission_can_create_an_admin_account_with_roles(): void
    {
        $admin = $this->adminWithManageAdmins();
        $role = Role::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
            'roles' => [$role->id],
        ]);

        $response->assertOk();
        $created = Admin::query()->where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertTrue($created->roles->contains($role));
    }

    public function test_an_admin_without_permission_cannot_create_an_admin_account(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'Should Not Exist',
            'email' => 'should-not-exist@example.com',
            'password' => 'password123',
            'roles' => [],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('admins', ['email' => 'should-not-exist@example.com']);
    }

    public function test_creating_an_admin_with_a_duplicate_email_fails_validation(): void
    {
        $admin = $this->adminWithManageAdmins();
        $existing = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'password' => 'password123',
            'roles' => [],
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_regular_user_cannot_access_the_admin_admins_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.admins.index'));

        $response->assertRedirect(route('admin.login'));
    }
}

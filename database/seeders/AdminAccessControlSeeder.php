<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed `AdminPermission` catalogue, the protected "Super Admin"
 * role holding all of it, and one default admin account so a fresh
 * `migrate --seed` has a working way into `/admin` (same reasoning as
 * `DatabaseSeeder`'s `test@example.com`: a documented, predictable account,
 * not a random one nobody can log into). Backed by `spatie/laravel-
 * permission` (TASKS.md 7.8, ADR-016) — role/permission assignment goes
 * through spatie's own methods (`syncPermissions()`/`assignRole()`), not
 * raw pivot writes, so its permission cache stays correctly invalidated.
 */
class AdminAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(AdminPermission::cases())->map(
            fn (AdminPermission $permission): Permission => Permission::query()->firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'admin'],
                ['label' => $permission->label(), 'module' => $permission->group()],
            ),
        );

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard_name' => 'admin', 'description' => 'Full access to every admin-panel capability.', 'is_system' => true],
        );

        $superAdmin->syncPermissions($permissions);

        $admin = Admin::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => 'password'],
        );

        if (! $admin->hasRole($superAdmin)) {
            $admin->assignRole($superAdmin);
        }
    }
}

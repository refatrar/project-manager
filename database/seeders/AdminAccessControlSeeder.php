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
 * not a random one nobody can log into).
 */
class AdminAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(AdminPermission::cases())->map(
            fn (AdminPermission $permission): Permission => Permission::query()->firstOrCreate(
                ['key' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            ),
        );

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Full access to every admin-panel capability.', 'is_system' => true],
        );

        $superAdmin->permissions()->sync($permissions->pluck('id'));

        Admin::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => 'password'],
        )->roles()->syncWithoutDetaching([$superAdmin->id]);
    }
}

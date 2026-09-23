<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Platform admins are not assigned roles. Drop the admin-guard role
     * and permission rows. Team-module roles stay on the `web` guard.
     */
    public function up(): void
    {
        $roleIds = DB::table('roles')->where('guard_name', 'admin')->pluck('id');
        $permissionIds = DB::table('permissions')->where('guard_name', 'admin')->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    public function down(): void
    {
        // Admin-guard roles are not restored. They were a panel-only
        // catalogue, and the admin account itself is unchanged.
    }
};

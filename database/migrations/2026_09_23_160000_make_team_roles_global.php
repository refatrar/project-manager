<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Team-module roles are one global set on the `web` guard, assigned
     * from the admin panel. Per-team copies are removed. Slug uniqueness
     * includes the guard so an admin-panel role and a team role can share
     * a slug while both have a null `team_id`.
     */
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNotNull('team_id')
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_scope_slug_unique');
            $table->unique(['team_scope', 'guard_name', 'slug'], 'roles_scope_guard_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_scope_guard_slug_unique');
            $table->unique(['team_scope', 'slug'], 'roles_scope_slug_unique');
        });
    }
};

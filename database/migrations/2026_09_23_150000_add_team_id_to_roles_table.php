<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Team-panel roles are per team (an Owner on team A is not the Owner
     * role row of team B). `team_id` null stays the platform-admin roles.
     * A generated `team_scope` keeps name/slug unique per team while still
     * rejecting two admin-guard roles with the same name: MySQL would
     * otherwise treat every NULL `team_id` as distinct in a unique index.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['name', 'guard_name']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('team_scope')->virtualAs('ifnull(`team_id`, 0)');
            $table->unique(['team_scope', 'name', 'guard_name'], 'roles_scope_name_guard_unique');
            $table->unique(['team_scope', 'slug'], 'roles_scope_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_scope_name_guard_unique');
            $table->dropUnique('roles_scope_slug_unique');
            $table->dropColumn('team_scope');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->unique(['name', 'guard_name']);
            $table->unique('slug');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns spatie's own tables don't ship but this app's admin-panel
     * UI/API contract needs, so migrating onto spatie is not a silent
     * feature regression: `is_system` (protects the seeded "Super Admin"
     * role from edits/deletion), `slug` (already computed by
     * `RoleController::store()`), `description`, and — the actual point
     * of TASKS.md 7.8's "module-wise custom permissions" — `module` and
     * `label` on `permissions`, since spatie's own `name` column is the
     * raw dot-notation permission key, not a human-readable grouping.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('guard_name');
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('description')->nullable()->after('slug');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->nullable()->after('guard_name');
            $table->string('label')->nullable()->after('module');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['module', 'label']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'slug', 'description']);
        });
    }
};

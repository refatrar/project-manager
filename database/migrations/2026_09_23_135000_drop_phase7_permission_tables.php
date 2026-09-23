<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7's hand-rolled admin-panel RBAC (`roles`, `permissions`,
     * `permission_role`, `admin_role`) is replaced by `spatie/laravel-
     * permission`'s own tables (`create_permission_tables`, the next
     * migration after this one) — see TASKS.md 7.8 and the forthcoming
     * ADR-016. `admins` itself is untouched; only the access-control
     * tables are dropped. One-way: there is no production data to
     * preserve (confirmed before running this).
     */
    public function up(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('admin_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    public function down(): void
    {
        // Deliberately irreversible — see the docblock above.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop project-level effort and budget. Hours stay on tasks and modules.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'estimated_hours')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['estimated_hours', 'budget', 'currency']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'estimated_hours')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('estimated_hours', 10, 2)->nullable()->after('actual_end_date');
            $table->decimal('budget', 14, 2)->nullable()->after('estimated_hours');
            $table->char('currency', 3)->nullable()->after('budget');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One user, project-scoped administrative authority, distinct from
     * `owner_id` (an accountability field the policies don't consult) and
     * from `ProjectMemberRole` (which allows any number of Owner/Manager/
     * Lead members with no single designated lead). Nullable: not every
     * project needs one set at creation.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_lead_id')->nullable()->after('owner_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_lead_id');
        });
    }
};

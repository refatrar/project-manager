<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_on');
            $table->unsignedInteger('total_tasks')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->unsignedInteger('in_progress_tasks')->default(0);
            $table->unsignedInteger('blocked_tasks')->default(0);
            $table->unsignedInteger('overdue_tasks')->default(0);
            $table->decimal('estimated_hours', 12, 2)->default(0);
            $table->decimal('logged_hours', 12, 2)->default(0);
            $table->decimal('remaining_hours', 12, 2)->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->unsignedInteger('merged_pull_requests_count')->default(0);
            $table->string('health', 16)->default('on_track');
            $table->timestamps();

            $table->unique(['project_id', 'sprint_id', 'snapshot_on'], 'progress_snapshots_project_sprint_date_unique');
            $table->index(['project_id', 'snapshot_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_progress_snapshots');
    }
};

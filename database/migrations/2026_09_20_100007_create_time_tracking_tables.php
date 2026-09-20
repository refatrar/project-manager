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
        Schema::create('user_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('capacity_hours', 5, 2)->default(0);
            $table->string('timezone', 64)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day_of_week', 'effective_from']);
            $table->index(['user_id', 'effective_from', 'effective_until']);
        });

        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('status', 16)->default('pending');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_full_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_hours', 6, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on']);
            $table->index(['status', 'starts_on']);
        });

        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('planned');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('hours_per_day', 5, 2);
            $table->unsignedTinyInteger('allocation_percentage')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on']);
            $table->index(['project_id', 'starts_on']);
            $table->index(['status', 'starts_on']);
        });

        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('todo_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('activity_type', 24)->default('development');
            $table->string('source', 16)->default('manual');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->date('logged_on');
            $table->boolean('is_billable')->default(true);
            $table->string('approval_status', 16)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['user_id', 'logged_on']);
            $table->index(['project_id', 'logged_on']);
            $table->index(['team_id', 'logged_on']);
            $table->index(['task_id', 'logged_on']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_logs');
        Schema::dropIfExists('resource_allocations');
        Schema::dropIfExists('time_off_requests');
        Schema::dropIfExists('user_work_schedules');
    }
};

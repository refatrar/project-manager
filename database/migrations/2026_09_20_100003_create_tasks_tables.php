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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('task_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('status', 32)->default('backlog');
            $table->string('review_status', 32)->nullable();
            $table->string('deployment_stage', 32)->nullable();
            $table->string('priority', 16)->default('medium');
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('logged_hours', 10, 2)->default(0);
            $table->decimal('remaining_hours', 8, 2)->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_billable')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'status', 'priority']);
            $table->index(['project_id', 'project_module_id', 'position']);
            $table->index(['status', 'due_at']);
            $table->index(['sprint_id', 'status']);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('assignee');
            $table->string('status', 24)->default('assigned');
            $table->decimal('allocated_hours', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'user_id', 'role']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 24)->default('blocked_by');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['task_id', 'related_task_id', 'type']);
            $table->index('related_task_id');
        });

        Schema::create('task_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['task_id', 'changed_at']);
        });

        Schema::create('label_task', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            $table->primary(['label_id', 'task_id']);
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_task');
        Schema::dropIfExists('task_status_histories');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
    }
};

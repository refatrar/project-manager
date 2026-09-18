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
        Schema::create('project_module_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_module_task_id')->index()->nullable()->references('id')->on('project_module_tasks')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('project_module_id')->index()->references('id')->on('project_modules')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('task_type_id')->index()->references('id')->on('task_types')->onUpdate('cascade')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['not_assigned', 'assigned', 'in_progress', 'completed', 'reassigned', 'in_review', 'approved', 'staging_done', 'live_done', 'rejected', 'cancelled'])->default('not_assigned');
            $table->foreignId('created_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('project_module_task_user', function (Blueprint $table) {
            $table->foreignId('project_module_task_id')->index()->references('id')->on('project_module_tasks')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('user_id')->index()->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_module_tasks');
    }
};

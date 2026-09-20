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
        Schema::create('project_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_modules')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('planning');
            $table->string('priority', 16)->default('medium');
            $table->unsignedInteger('position')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'position']);
        });

        Schema::create('project_module_scope', function (Blueprint $table) {
            $table->foreignId('project_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_module_id', 'scope_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_module_scope');
        Schema::dropIfExists('project_modules');
    }
};

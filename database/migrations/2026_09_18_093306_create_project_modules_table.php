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
            $table->foreignId('project_module_id')->nullable()->index()->references('id')->on('project_modules')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('project_id')->index()->references('id')->on('projects')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('project_module_scope', function (Blueprint $table) {
            $table->foreignId('project_module_id')->index()->references('id')->on('project_modules')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('scope_id')->index()->references('id')->on('scopes')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_modules');
    }
};

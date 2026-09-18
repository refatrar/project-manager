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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['active', 'completed', 'on_hold', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_members');
    }
};

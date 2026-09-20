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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type', 24)->default('general');
            $table->string('status', 24)->default('scheduled');
            $table->longText('agenda')->nullable();
            $table->longText('minutes')->nullable();
            $table->longText('decisions')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('minutes_published_at')->nullable();
            $table->foreignId('organized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['team_id', 'scheduled_start']);
            $table->index(['project_id', 'scheduled_start']);
            $table->index(['status', 'scheduled_start']);
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('role', 24)->default('participant');
            $table->string('attendance_status', 24)->default('invited');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'attendance_status']);
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_discussed')->default(false);
            $table->timestamps();

            $table->index(['meeting_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_agenda_items');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single, platform-wide weekly capacity template (replaces the
     * per-user `user_work_schedules` — see the companion drop migration),
     * versioned by `effective_from`/`effective_until` the same way the
     * per-user table was, so a past change stays historically accurate.
     * There is only ever one row per `(day_of_week, effective_from)`.
     */
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('capacity_hours', 5, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['day_of_week', 'effective_from']);
            $table->index(['effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};

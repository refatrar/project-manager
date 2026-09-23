<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user schedules are retired in favor of the single global
     * `work_schedules` template (`create_work_schedules_table`, above).
     * One-way: there is no production data to preserve (confirmed before
     * running this), so `down()` intentionally does not restore the old
     * per-user shape.
     */
    public function up(): void
    {
        Schema::dropIfExists('user_work_schedules');
    }

    public function down(): void
    {
        // Deliberately irreversible — see the docblock above.
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->after('email');
            $table->string('address')->nullable()->after('mobile');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('zip')->nullable()->after('state');
            $table->string('country')->nullable()->after('zip');
            $table->string('profile_picture')->nullable()->after('country');
            $table->string('role')->nullable()->after('profile_picture');
            $table->enum('status', ['active', 'blocked', 'inactive'])->default('active')->after('role');
            $table->foreignId('created_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('updated_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('deleted_by')->index()->nullable()->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile');
            $table->dropColumn('address');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('zip');
            $table->dropColumn('country');
            $table->dropColumn('profile_picture');
            $table->dropColumn('role');
            $table->dropColumn('status');
            $table->dropColumn('deleted_at');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_by');
            $table->dropColumn('deleted_by');
        });

    }
};

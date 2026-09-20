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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['commentable_type', 'commentable_id', 'created_at'], 'comments_commentable_created_index');
        });

        Schema::create('comment_mentions', function (Blueprint $table) {
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['comment_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id'], 'attachments_attachable_index');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event', 48);
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('comments');
    }
};

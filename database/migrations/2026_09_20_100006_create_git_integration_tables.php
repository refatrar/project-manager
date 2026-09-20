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
        Schema::create('git_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('external_id')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'username']);
            $table->unique(['provider', 'email']);
            $table->index(['user_id', 'provider']);
        });

        Schema::create('git_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 16);
            $table->string('full_name');
            $table->string('external_id')->nullable();
            $table->string('default_branch')->default('main');
            $table->string('visibility', 16)->nullable();
            $table->string('web_url')->nullable();
            $table->string('clone_url')->nullable();
            $table->string('api_base_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('webhook_external_id')->nullable();
            $table->string('sync_status', 16)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['team_id', 'provider', 'full_name']);
            $table->index(['project_id', 'is_active']);
        });

        Schema::create('git_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('head_commit_sha', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_merged')->default(false);
            $table->unsignedInteger('ahead_count')->nullable();
            $table->unsignedInteger('behind_count')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['git_repository_id', 'name']);
            $table->index(['task_id', 'is_merged']);
        });

        Schema::create('git_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_repository_id')->constrained()->cascadeOnDelete();
            $table->string('sha', 40);
            $table->string('parent_sha', 40)->nullable();
            $table->text('message');
            $table->string('branch')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('committer_name')->nullable();
            $table->string('committer_email')->nullable();
            $table->unsignedInteger('additions')->nullable();
            $table->unsignedInteger('deletions')->nullable();
            $table->unsignedInteger('changed_files')->nullable();
            $table->string('web_url')->nullable();
            $table->timestamp('authored_at')->nullable();
            $table->timestamp('committed_at');
            $table->timestamps();

            $table->unique(['git_repository_id', 'sha']);
            $table->index(['git_repository_id', 'committed_at']);
            $table->index(['author_id', 'committed_at']);
        });

        Schema::create('git_commit_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_commit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('link_source', 24)->default('commit_message');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['git_commit_id', 'task_id']);
            $table->index('task_id');
        });

        Schema::create('git_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('state', 16)->default('open');
            $table->string('source_branch');
            $table->string('target_branch');
            $table->string('author_name')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('commits_count')->default(0);
            $table->unsignedInteger('additions')->nullable();
            $table->unsignedInteger('deletions')->nullable();
            $table->unsignedInteger('changed_files')->nullable();
            $table->unsignedInteger('review_comments_count')->default(0);
            $table->string('web_url')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['git_repository_id', 'number']);
            $table->index(['state', 'opened_at']);
            $table->index('task_id');
        });

        Schema::create('git_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_repository_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->string('external_event_id')->nullable();
            $table->string('ref')->nullable();
            $table->string('before_sha', 40)->nullable();
            $table->string('after_sha', 40)->nullable();
            $table->unsignedInteger('commits_count')->default(0);
            $table->string('actor_name')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['git_repository_id', 'external_event_id']);
            $table->index(['git_repository_id', 'event_type', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('git_events');
        Schema::dropIfExists('git_pull_requests');
        Schema::dropIfExists('git_commit_task');
        Schema::dropIfExists('git_commits');
        Schema::dropIfExists('git_branches');
        Schema::dropIfExists('git_repositories');
        Schema::dropIfExists('git_identities');
    }
};

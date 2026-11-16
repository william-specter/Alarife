<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title', 200);
            $table->text('excerpt')->nullable();
            $table->jsonb('content')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'revision_number']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('snapshot_path', 500);
            $table->string('theme_key', 40);
            $table->string('theme_version', 20);
            $table->jsonb('theme_settings')->nullable();
            $table->unsignedInteger('topic_count')->default(0);
            $table->unsignedInteger('article_count')->default(0);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'version']);
        });

        Schema::create('encyclopedia_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('template_key', 40);
            $table->string('template_version', 20);
            $table->string('renderer_version', 20);
            $table->jsonb('options')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('archive_path', 500)->nullable();
            $table->unsignedBigInteger('archive_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('cache_key', 64);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['encyclopedia_id', 'created_at']);
            $table->index(['cache_key', 'status']);
            $table->index(['status']);
        });

        Schema::create('article_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->string('from_slug', 180);
            $table->foreignId('to_article_id')->constrained('articles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'from_slug']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['encyclopedia_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('article_redirects');
        Schema::dropIfExists('encyclopedia_exports');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('article_revisions');
    }
};

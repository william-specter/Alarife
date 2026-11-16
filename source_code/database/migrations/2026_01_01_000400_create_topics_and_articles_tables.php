<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'slug']);
            $table->index(['encyclopedia_id', 'parent_id', 'sort_order']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 180);
            $table->text('excerpt')->nullable();
            $table->jsonb('content')->nullable();
            $table->unsignedSmallInteger('content_schema_version')->default(1);
            $table->foreignId('featured_image_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->enum('status', ['draft', 'in_review', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['encyclopedia_id', 'slug']);
            $table->index(['encyclopedia_id', 'status']);
            $table->index(['topic_id', 'sort_order']);
            $table->index(['published_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE articles ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(excerpt, '')), 'B') ||
                    setweight(to_tsvector('simple', coalesce(content::text, '')), 'C')
                ) STORED
            SQL);

            Schema::getConnection()->statement(
                'CREATE INDEX articles_search_vector_index ON articles USING GIN (search_vector)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('topics');
    }
};

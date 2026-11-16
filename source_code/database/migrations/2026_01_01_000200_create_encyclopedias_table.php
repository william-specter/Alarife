<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encyclopedias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 120)->unique();
            $table->string('subtitle', 240)->nullable();
            $table->text('description')->nullable();
            $table->string('language', 8)->default('en');
            $table->enum('visibility', ['private', 'unlisted', 'public'])->default('private');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('theme_key', 40)->default('classic');
            $table->string('theme_version', 20)->default('1.0.0');
            $table->jsonb('theme_settings')->nullable();
            $table->enum('cover_style', ['hero', 'editorial', 'cards'])->default('hero');
            $table->boolean('export_enabled')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id']);
            $table->index(['status', 'visibility']);
            $table->index(['published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encyclopedias');
    }
};

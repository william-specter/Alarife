<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('checksum', 64);
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'checksum']);
            $table->index(['encyclopedia_id']);
        });

        Schema::create('encyclopedia_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['editor', 'author'])->default('author');
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'user_id']);
        });

        Schema::create('encyclopedia_covers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('caption', 300)->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->timestamps();

            $table->unique(['encyclopedia_id', 'position']);
            $table->unique(['encyclopedia_id', 'media_asset_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE encyclopedia_covers ADD CONSTRAINT encyclopedia_covers_position_check CHECK (position BETWEEN 1 AND 3)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('encyclopedia_covers');
        Schema::dropIfExists('encyclopedia_members');
        Schema::dropIfExists('media_assets');
    }
};

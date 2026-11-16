<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_encyclopedia_id')->constrained('encyclopedias')->cascadeOnDelete();
            $table->foreignId('target_encyclopedia_id')->constrained('encyclopedias')->cascadeOnDelete();
            $table->enum('target_type', ['article', 'topic']);
            $table->unsignedBigInteger('target_id');
            $table->string('note', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_encyclopedia_id', 'target_encyclopedia_id', 'target_type', 'target_id'],
                'cross_references_unique_target'
            );
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_references');
    }
};

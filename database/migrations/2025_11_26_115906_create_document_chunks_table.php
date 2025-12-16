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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64)->nullable();
            $table->integer('token_count')->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->string('qdrant_point_id', 100)->nullable();
            $table->string('qdrant_collection', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'document_id']);
            $table->index(['version_id']);
            $table->index(['qdrant_point_id']);
            $table->index(['content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};

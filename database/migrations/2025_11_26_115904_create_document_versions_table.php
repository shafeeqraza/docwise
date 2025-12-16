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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->enum('processing_state', ['pending', 'parsing', 'chunking', 'embedding', 'completed', 'failed'])->default('pending');
            $table->integer('chunk_count')->default(0);
            $table->integer('token_count')->default(0);
            $table->string('embedding_model', 100)->nullable();
            $table->json('chunk_strategy')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version'], 'unique_document_version');
            $table->index(['processing_state']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};

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
        Schema::create('ingestion_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('job_type', ['parse_document', 'generate_embeddings', 'sync_qdrant', 'cleanup']);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->integer('priority')->default(0);
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->json('payload')->nullable();
            $table->json('progress_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id']);
            $table->index(['status']);
            $table->index(['job_type']);
            $table->index(['queued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_jobs');
    }
};

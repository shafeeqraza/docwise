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
        Schema::create('usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('metric_type', ['daily', 'weekly', 'monthly']);
            $table->integer('documents_uploaded')->default(0);
            $table->integer('documents_processed')->default(0);
            $table->integer('chunks_created')->default(0);
            $table->integer('embeddings_generated')->default(0);
            $table->integer('chat_sessions')->default(0);
            $table->integer('chat_messages')->default(0);
            $table->integer('tokens_prompt')->default(0);
            $table->integer('tokens_completion')->default(0);
            $table->integer('vector_queries')->default(0);
            $table->integer('api_calls')->default(0);
            $table->bigInteger('storage_bytes')->default(0);
            $table->json('costs')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_start', 'metric_type'], 'unique_company_period');
            $table->index(['company_id']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
    }
};

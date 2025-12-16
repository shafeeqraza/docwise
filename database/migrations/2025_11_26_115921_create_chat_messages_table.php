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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->enum('content_type', ['text', 'markdown', 'html'])->default('text');
            $table->integer('tokens_prompt')->nullable();
            $table->integer('tokens_completion')->nullable();
            $table->string('model_used', 100)->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();
            $table->json('raw_llm_response')->nullable();
            $table->json('citations')->nullable();
            $table->timestamps();

            $table->index(['session_id']);
            $table->index(['role']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};

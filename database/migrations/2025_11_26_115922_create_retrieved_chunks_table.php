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
        Schema::create('retrieved_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('chunk_id')->constrained('document_chunks')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->decimal('similarity_score', 6, 4);
            $table->integer('rank_position');
            $table->boolean('used_in_context')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['message_id']);
            $table->index(['chunk_id']);
            $table->index(['similarity_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retrieved_chunks');
    }
};

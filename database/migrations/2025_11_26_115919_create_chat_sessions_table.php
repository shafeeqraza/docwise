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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id')->nullable();
            $table->enum('channel', ['web', 'api', 'widget', 'slack', 'teams'])->default('web');
            $table->string('title', 500)->nullable();
            $table->enum('status', ['active', 'resolved', 'escalated', 'archived'])->default('active');
            $table->string('language', 10)->default('en');
            $table->json('user_metadata')->nullable();
            $table->json('context')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('satisfaction_rating')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id']);
            $table->index(['external_user_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};

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
        Schema::create('api_key_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('company_api_keys')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->uuid('request_id')->unique();
            $table->uuid('session_id')->nullable();
            $table->integer('tokens_prompt')->default(0);
            $table->integer('tokens_completion')->default(0);
            $table->integer('latency_ms');
            $table->integer('status_code');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['api_key_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_key_usage_logs');
    }
};

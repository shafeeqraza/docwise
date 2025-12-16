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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['active', 'suspended', 'trial'])->default('active');
            $table->string('subscription_plan', 50)->default('basic');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->date('next_billing_date')->nullable();
            $table->enum('payment_status', ['active', 'past_due', 'cancelled'])->default('active');
            $table->boolean('allow_overages')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['slug']);
            $table->index(['email']);
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['subscription_plan']);
            $table->index(['payment_status']);
        });

        // Add foreign key constraint to users table now that companies table exists
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::dropIfExists('companies');
    }
};

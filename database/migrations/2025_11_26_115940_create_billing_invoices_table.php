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
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('base_amount', 10, 2);
            $table->decimal('overage_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft');
            $table->string('stripe_invoice_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['company_id']);
            $table->index(['status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};

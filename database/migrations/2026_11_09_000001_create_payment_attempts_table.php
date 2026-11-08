<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per self-serve payment attempt (M-Pesa STK push or card
        // checkout). Pending attempts must NOT live in
        // subscription_payments: that table feeds the platform revenue
        // graphs and only ever holds completed income.
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('gateway'); // mpesa | paystack
            $table->string('status')->default('pending'); // pending | succeeded | failed
            $table->string('gateway_reference')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Damage line items against a deposit refund, photographed as proof.
     * They reduce the payout: the landlord approves the net amount, not
     * the gross deposit.
     */
    public function up(): void
    {
        Schema::create('deposit_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refund_id')->constrained('deposit_refunds')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['refund_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_deductions');
    }
};

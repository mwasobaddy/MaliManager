<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paid tracking for deposit refunds. An approved refund is a promise;
     * these columns record the actual payout — who sent it, when, and the
     * payment reference for reconciling against M-Pesa/bank statements.
     */
    public function up(): void
    {
        Schema::table('deposit_refunds', function (Blueprint $table) {
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_reference', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid_at', 'paid_reference']);
        });
    }
};

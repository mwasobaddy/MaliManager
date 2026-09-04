<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // The lease episode being paid for. occupant_id is denormalized so
            // a receipt can be rendered even if the lease is later ended.
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occupant_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->date('paid_on');

            // Rent period this payment covers (optional).
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->string('status')->default('received');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'paid_on']);
            $table->index(['organization_id', 'status']);
            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();

            /*
             * Integer cents, not a decimal column. A refund is money that gets
             * added up and compared against the lease's deposit; floats accumulate
             * representational error and a deposit is exactly the kind of amount
             * that must reconcile to the cent.
             */
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('KES');

            $table->text('reason');
            $table->string('status')->default('pending')->index();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_refunds');
    }
};

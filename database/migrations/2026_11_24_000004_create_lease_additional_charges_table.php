<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // fixed: a flat amount every period (parking, pets).
            // one_time: billed once against the next invoice, then done.
            $table->string('kind')->default('fixed');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            // Income ledger code, same bookkeeping as utility charges.
            $table->string('gl_code', 32)->nullable();
            $table->boolean('is_active')->default(true);
            // One-time extras are spent once invoiced; Phase B stamps this.
            $table->timestamp('consumed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'lease_id']);
            $table->index(['lease_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_additional_charges');
    }
};

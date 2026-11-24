<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // metered: billed per unit of consumption at `rate`.
            // fixed: flat amount per unit per period, stored per unit.
            // one_time: billed once against the next invoice, then done.
            $table->string('kind')->default('fixed');
            // Metered utility being billed (power, water, gas). Fixed and
            // one-time charges (garbage, security, move-in fees) leave it null.
            $table->string('utility')->nullable();
            // Per-unit price for metered charges (KES per kWh/m³).
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'property_id']);
            $table->index(['property_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_charges');
    }
};

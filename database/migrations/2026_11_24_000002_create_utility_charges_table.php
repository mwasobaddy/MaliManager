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
            // shared: a master-meter bill (`master_amount`) split across the
            // assigned units by `allocation` (RUBS for buildings with one
            // water meter and no submeters). Single fees live on the lease
            // as additional charges, never here.
            $table->string('kind')->default('fixed');
            // Metered utility being billed (power, water, gas). Fixed
            // charges (garbage, security) leave it null.
            $table->string('utility')->nullable();
            // Per-unit price for metered charges (KES per kWh/m³).
            $table->decimal('rate', 14, 4)->nullable();
            // Shared-bill split basis: `equal` or `occupants`. Only read for
            // the shared kind; other kinds leave it null.
            $table->string('allocation', 16)->nullable();
            // The current master bill total for a shared charge, refreshed
            // by the landlord when each new master bill arrives. Per-period
            // invoicing (Phase B) will snapshot it per invoice instead.
            $table->decimal('master_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->boolean('is_active')->default(true);

            // Income ledger code (e.g. 4100-utilities). Pure bookkeeping:
            // Phase B posts invoice lines against it, and Buildium-style
            // separation keeps rent, fees and utilities apart from day one.
            $table->string('gl_code', 32)->nullable();

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

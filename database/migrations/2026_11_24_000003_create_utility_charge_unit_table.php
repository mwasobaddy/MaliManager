<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_charge_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();

            // Flat amount billed per period for fixed charges. Metered
            // charges leave it null and bill consumption × rate instead.
            $table->decimal('amount', 14, 2)->nullable();
            // Metered billing watermark: consumption at or below this reading
            // was already accounted for, so only newer readings bill. Set to
            // the latest reading when the unit is attached, so a new charge
            // never back-bills history the landlord already handled.
            $table->foreignId('last_billed_reading_id')->nullable()->constrained('utility_readings')->nullOnDelete();

            $table->timestamps();

            $table->unique(['utility_charge_id', 'unit_id']);
            $table->index(['unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_charge_unit');
    }
};

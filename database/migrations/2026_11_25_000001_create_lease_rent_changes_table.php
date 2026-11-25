<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_rent_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();

            // First day this rent applies. The inception row (effective on
            // the lease start) is seeded at creation, so every billed
            // period has exactly one rate — later amendments never rewrite
            // what earlier periods owed.
            $table->date('effective_date');
            $table->decimal('rent_amount', 14, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lease_id', 'effective_date']);
            $table->index(['lease_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_rent_changes');
    }
};

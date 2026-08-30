<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->comment('Audit: stancl tenancy id at write time');
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('land_parcel_id')->nullable()->constrained('land_parcels')->nullOnDelete();
            $table->foreignId('land_parcel_section_id')->nullable()->constrained('land_parcel_sections')->nullOnDelete();
            $table->foreignId('occupant_id')->nullable()->constrained()->nullOnDelete();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->decimal('rent_amount', 14, 2)->nullable();
            $table->string('rent_frequency')->nullable()->comment('daily|weekly|monthly|yearly');
            $table->string('currency', 3)->nullable();
            $table->decimal('deposit', 14, 2)->nullable();
            $table->text('agreement_text')->nullable()->comment('In-system composed lease agreement');
            $table->string('status')->default('active')->comment('active|ended');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'status']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'unit_id']);
            $table->index(['organization_id', 'property_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};

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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            // A pasted map link (Google Maps, Plus Code, OpenStreetMap). Kept
            // separate from `address`: one is prose a human reads, the other is
            // something a button can open, and conflating them loses both.
            $table->string('location_url')->nullable();
            $table->foreignId('property_type_id')
                ->nullable()
                ->constrained('property_types')
                ->nullOnDelete();
            $table->string('status')->default('active');
            $table->json('settings')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'name']);

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['name', 'slug', 'address', 'city', 'status'], 'properties_fulltext_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

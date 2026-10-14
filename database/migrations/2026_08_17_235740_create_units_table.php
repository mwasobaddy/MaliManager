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
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Photos live at {org}/{property-slug}/{unit-slug}/photos. The name
            // is free text and is not unique across properties, so the folder
            // cannot be keyed by it without two properties' "A1" sharing one
            // directory. Slug is unique per property and fixed at creation, so
            // the path is stable even if the unit is later renamed.
            $table->string('slug');
            $table->string('status')->default('vacant');
            $table->string('type')->nullable();
            $table->decimal('monthly_rent', 12, 2)->nullable();
            $table->decimal('deposit', 12, 2)->nullable();
            $table->json('settings')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'name']);
            $table->unique(['property_id', 'slug']);
            $table->index(['property_id', 'status']);

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['name', 'type', 'status'], 'units_fulltext_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

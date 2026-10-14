<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A catalog of property types, scoped to one organization.
     *
     * Organization-scoped rather than global on purpose. A single global table
     * would let any owner rename or delete a type that every other tenant's
     * properties depend on. Each organization gets its own copy of the seeded
     * defaults and can then edit, add or remove them freely.
     *
     * `category` splits the catalog into residential and commercial. It is a
     * column rather than a second table because the split is fixed, binary and
     * queried everywhere — a lookup table would add a join to every property
     * list for no gain.
     *
     * Deliberately dated one second before `create_properties_table`, which
     * holds a foreign key to this table. A later filename would let the
     * constraint reference a table that does not exist yet.
     */
    public function up(): void
    {
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('category', ['residential', 'commercial'])->default('residential');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Slugs are the stable key used for storage paths and CSV imports,
            // so they must not repeat inside an organization.
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
    }
};

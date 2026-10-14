<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A platform-wide catalog of property types.
     *
     * Global, with no organization_id: the catalogue is central
     * configuration curated by platform admins, not something each tenant
     * copies and edits. A per-org table was the earlier shape, but it meant the
     * 52 seeded entries had to be written once per organization and a type
     * renamed by one owner diverged from everyone else's.
     *
     * Every tenant's property form still reads this table, so removing a type
     * affects everyone — which is why the delete confirmation reports how many
     * properties reference it, and why deletion never cascades: properties are
     * kept and simply become untyped.
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

            $table->string('name');
            $table->string('slug');
            $table->enum('category', ['residential', 'commercial'])->default('residential');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One catalogue, so both name and slug are globally unique. The
            // slug is the stable key used for CSV imports and storage paths.
            $table->unique('slug');
            $table->unique('name');
            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
    }
};

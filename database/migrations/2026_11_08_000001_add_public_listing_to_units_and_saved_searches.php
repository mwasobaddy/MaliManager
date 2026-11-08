<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Null means "follow the organization": listed only when the org
            // allows public listings. True/false overrides it per unit, so a
            // landlord can hold back a unit under renovation without turning
            // the whole portfolio off.
            $table->boolean('list_publicly')->nullable()->after('status');
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // The browse filters as submitted: location text, min/max rent,
            // unit type. Stored verbatim so re-running is a redirect, not a
            // second form.
            $table->json('filters');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('list_publicly');
        });
    }
};

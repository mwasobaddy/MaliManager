<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One occupant row per tenancy: the same person may hold several
     * units, each as its own occupant record sharing the person (and the
     * login, the contact details, the rental history). The org-wide
     * uniqueness moves from the schema to the creation path, which reuses
     * the active row already holding a unit instead of duplicating it.
     */
    public function up(): void
    {
        // The organization_id foreign key rides on the unique index's
        // leftmost column, so plant a plain index before dropping it.
        Schema::table('occupants', function (Blueprint $table): void {
            $table->index('organization_id');
        });

        Schema::table('occupants', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::table('occupants', function (Blueprint $table): void {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('occupants', function (Blueprint $table): void {
            $table->unique(['organization_id', 'person_id']);
        });
    }
};

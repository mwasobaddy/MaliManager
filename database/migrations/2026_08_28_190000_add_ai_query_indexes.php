<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes that back the semantic-layer analytics queries the AI assistant
     * runs. Most queries are org-scoped (so (organization_id, <dimension>)
     * composites win), but the platform/admin assistant queries whole tables
     * with no org filter, so the commonly-grouped low-cardinality dimensions
     * also get a standalone leading-column index.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'city']);
            $table->index('status');
            $table->index('city');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->index(['property_id', 'status']);
            $table->index(['property_id', 'type']);
            $table->index('status');
            $table->index('type');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->index(['organization_id', 'ends_at']);
            $table->index(['organization_id', 'starts_at']);
            $table->index(['organization_id', 'currency']);
            $table->index('status');
            $table->index('currency');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['organization_id', 'currency']);
            $table->index('category');
            $table->index('currency');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'resolved_at']);
            $table->index('raised_by');
            $table->index('priority');
        });

        Schema::table('land_parcels', function (Blueprint $table) {
            $table->index(['organization_id', 'zoning']);
            $table->index(['organization_id', 'city']);
            $table->index('city');
            $table->index('zoning');
            $table->index('status');
        });

        Schema::table('land_parcel_sections', function (Blueprint $table) {
            $table->index(['land_parcel_id', 'status']);
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->index(['organization_id', 'property_id']);
            $table->index(['organization_id', 'unit_id']);
            $table->index('inspection_date');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'property_id']);
            $table->dropIndex(['organization_id', 'unit_id']);
            $table->dropIndex('inspection_date');
        });

        Schema::table('land_parcel_sections', function (Blueprint $table) {
            $table->dropIndex(['land_parcel_id', 'status']);
        });

        Schema::table('land_parcels', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'zoning']);
            $table->dropIndex(['organization_id', 'city']);
            $table->dropIndex('city');
            $table->dropIndex('zoning');
            $table->dropIndex('status');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'priority']);
            $table->dropIndex(['organization_id', 'resolved_at']);
            $table->dropIndex('raised_by');
            $table->dropIndex('priority');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'currency']);
            $table->dropIndex('category');
            $table->dropIndex('currency');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'ends_at']);
            $table->dropIndex(['organization_id', 'starts_at']);
            $table->dropIndex(['organization_id', 'currency']);
            $table->dropIndex('status');
            $table->dropIndex('currency');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'status']);
            $table->dropIndex(['property_id', 'type']);
            $table->dropIndex('status');
            $table->dropIndex('type');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'city']);
            $table->dropIndex('status');
            $table->dropIndex('city');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('status');
        });
    }
};

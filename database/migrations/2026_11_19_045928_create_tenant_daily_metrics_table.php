<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per organization per day: cumulative counters the trial
        // funnel and feature adoption read from. Counts are point-in-time
        // (deleted records stop counting); first-ever dates live on the
        // source rows' created_at, which the funnel queries directly.
        Schema::create('tenant_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedInteger('properties_count')->default(0);
            $table->unsignedInteger('units_count')->default(0);
            $table->unsignedInteger('occupants_count')->default(0);
            $table->unsignedInteger('active_leases_count')->default(0);
            $table->decimal('rent_month_to_date', 14, 2)->default(0);
            $table->unsignedInteger('maintenance_open_count')->default(0);
            $table->boolean('uses_leases')->default(false);
            $table->boolean('uses_maintenance')->default(false);
            $table->boolean('uses_land')->default(false);
            $table->boolean('uses_mpesa_rent')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'captured_on']);
            $table->index('captured_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_daily_metrics');
    }
};

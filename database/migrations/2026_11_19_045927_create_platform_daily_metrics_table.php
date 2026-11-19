<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One rollup row per day for platform trend charts (MRR history,
        // GMV, unit growth). Written by metrics:snapshot; the dashboard
        // reads it, never live-aggregates history. Money is decimal per
        // the money rule, even where values are whole shillings.
        Schema::create('platform_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('captured_on')->unique();
            $table->decimal('mrr', 14, 2)->default(0);
            $table->unsignedInteger('paying_organizations')->default(0);
            $table->unsignedInteger('active_organizations')->default(0);
            $table->unsignedInteger('trials')->default(0);
            $table->unsignedInteger('past_due')->default(0);
            $table->unsignedInteger('signups_month_to_date')->default(0);
            $table->decimal('income_month_to_date', 14, 2)->default(0);
            $table->unsignedInteger('properties_total')->default(0);
            $table->unsignedInteger('units_total')->default(0);
            $table->decimal('rent_month_to_date', 14, 2)->default(0);
            $table->unsignedInteger('maintenance_open')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_daily_metrics');
    }
};

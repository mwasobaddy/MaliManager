<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-unit billing frequency plus honest amount naming.
     *
     * `monthly_rent` lied the moment a unit billed daily: 2500 a day is not
     * a monthly rent of 2500. The amount now means "per `rent_frequency`
     * period" and reporting normalizes to monthly equivalents. Leases gain
     * the two remaining due-date shapes (weekday for weekly, month for
     * yearly) plus `overdue_since`, which the 1am flag job maintains so the
     * 3am reminder job — and the dashboard — read a date, not a recompute.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('rent_frequency')->default('monthly')->after('type');
            $table->renameColumn('monthly_rent', 'rent_amount');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedTinyInteger('rent_due_weekday')->nullable()->after('rent_due_day');
            $table->unsignedTinyInteger('rent_due_month')->nullable()->after('rent_due_weekday');
            $table->date('overdue_since')->nullable()->after('last_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['rent_due_weekday', 'rent_due_month', 'overdue_since']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->renameColumn('rent_amount', 'monthly_rent');
            $table->dropColumn('rent_frequency');
        });
    }
};

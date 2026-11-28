<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled rent increments (annual %, effective date) live on the
     * lease as JSON so existing leases need no change: null means no
     * scheduled increment, and the renewal dialog previews the rest.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->json('rent_increment_schedule')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->dropColumn('rent_increment_schedule');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time repair: unit occupancy flags were write-only (nothing flipped
     * them on assignment or move-out), so dashboards could show "occupied"
     * units with no active lease. Recompute vacant/occupied from active
     * open-ended leases; maintenance flags are staff-owned and untouched.
     */
    public function up(): void
    {
        $occupiedUnitIds = DB::table('leases')
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->whereNotNull('unit_id')
            ->distinct()
            ->pluck('unit_id');

        DB::table('units')
            ->whereIn('status', ['vacant', 'occupied'])
            ->whereNull('deleted_at')
            ->whereIn('id', $occupiedUnitIds)
            ->update(['status' => 'occupied']);

        DB::table('units')
            ->whereIn('status', ['vacant', 'occupied'])
            ->whereNull('deleted_at')
            ->whereNotIn('id', $occupiedUnitIds)
            ->update(['status' => 'vacant']);
    }

    /**
     * Data repair — nothing to reverse.
     */
    public function down(): void
    {
        //
    }
};

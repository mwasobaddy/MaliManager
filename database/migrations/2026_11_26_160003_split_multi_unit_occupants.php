<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time reshape for the per-tenancy grain: occupants holding
     * several units split into one row per unit (the first unit stays on
     * the original row), and the leases and payments that name an
     * occupant follow their unit onto the new row. Person records, users,
     * and login are untouched — identity never splits, only the tenancy
     * rows do.
     */
    public function up(): void
    {
        $occupants = DB::table('occupants')
            ->select('id', 'organization_id', 'person_id', 'status', 'created_by', 'created_at', 'updated_at', 'deleted_at')
            ->get();

        foreach ($occupants as $occupant) {
            $unitIds = DB::table('occupant_unit')
                ->where('occupant_id', $occupant->id)
                ->orderBy('unit_id')
                ->pluck('unit_id')
                ->all();

            if (count($unitIds) < 2) {
                continue;
            }

            // The first unit stays on the original row; the rest move out.
            $splitUnitIds = array_slice($unitIds, 1);

            foreach ($splitUnitIds as $unitId) {
                $newId = DB::table('occupants')->insertGetId([
                    'organization_id' => $occupant->organization_id,
                    'person_id' => $occupant->person_id,
                    'status' => $occupant->status,
                    'created_by' => $occupant->created_by,
                    'created_at' => $occupant->created_at ?? now(),
                    'updated_at' => $occupant->updated_at ?? now(),
                    'deleted_at' => $occupant->deleted_at,
                ]);

                DB::table('occupant_unit')
                    ->where('occupant_id', $occupant->id)
                    ->where('unit_id', $unitId)
                    ->update(['occupant_id' => $newId]);

                $movedLeaseIds = DB::table('leases')
                    ->where('occupant_id', $occupant->id)
                    ->where('unit_id', $unitId)
                    ->pluck('id');

                DB::table('leases')
                    ->whereIn('id', $movedLeaseIds)
                    ->update(['occupant_id' => $newId]);

                DB::table('payments')
                    ->whereIn('lease_id', $movedLeaseIds)
                    ->where('occupant_id', $occupant->id)
                    ->update(['occupant_id' => $newId]);
            }
        }
    }

    /**
     * Data reshape — nothing to reverse.
     */
    public function down(): void
    {
        //
    }
};

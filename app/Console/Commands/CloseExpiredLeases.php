<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Unit;
use Illuminate\Console\Command;

/**
 * Nightly lease closer. A lease whose end date has passed is history even
 * if nobody clicked anything: it flips to ended, the unit re-reads its
 * occupancy, and an occupant left with no active lease falls back to
 * inactive. The occupant is NOT moved out — an expired lease with the
 * tenant still in place is a holdover, not a departure; move-out stays an
 * explicit staff act.
 */
class CloseExpiredLeases extends Command
{
    protected $signature = 'leases:close-expired';

    protected $description = 'End leases past their end date and refresh unit and occupant flags';

    public function handle(): int
    {
        $closed = 0;

        Lease::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($leases) use (&$closed): void {
                foreach ($leases as $lease) {
                    $lease->update(['status' => 'ended']);

                    if ($lease->unit_id !== null) {
                        $this->refreshUnitStatus((int) $lease->unit_id);
                    }

                    $occupant = $lease->occupant;

                    if ($occupant !== null && $occupant->status !== 'moved_out') {
                        $hasActive = Lease::query()
                            ->where('person_id', $lease->person_id)
                            ->where('status', 'active')
                            ->whereNull('ends_at')
                            ->exists();

                        if (! $hasActive) {
                            $occupant->update(['status' => 'inactive']);
                        }
                    }

                    $closed++;
                }
            });

        $this->info("Expired leases closed: {$closed}.");

        return self::SUCCESS;
    }

    private function refreshUnitStatus(int $unitId): void
    {
        $unit = Unit::find($unitId);

        if (! $unit || ! in_array($unit->status, ['vacant', 'occupied'], true)) {
            return;
        }

        $occupied = Lease::where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        $unit->fill(['status' => $occupied ? 'occupied' : 'vacant'])->save();
    }
}

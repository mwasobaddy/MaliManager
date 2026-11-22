<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\RentArrearsService;
use Illuminate\Console\Command;

/**
 * Nightly overdue marker, ahead of the 3am reminders. For every active
 * lease, stores the first unpaid due date in `overdue_since` (and clears it
 * when the lease catches up or ends) so the dashboard and the reminder job
 * read a date instead of recomputing one. Quiet saves: a derived flag must
 * not flood the activity log every night.
 */
class FlagOverdueRent extends Command
{
    protected $signature = 'rent:flag-overdue';

    protected $description = 'Mark newly-overdue leases with their first unpaid due date, clear the paid-up';

    public function handle(RentArrearsService $arrears): int
    {
        $flagged = 0;
        $cleared = 0;

        Lease::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString()))
            ->orderBy('id')
            ->chunkById(200, function ($leases) use ($arrears, &$flagged, &$cleared): void {
                foreach ($leases as $lease) {
                    $date = $arrears->firstOverdueDate($lease)?->toDateString();

                    if ($lease->overdue_since?->toDateString() === $date) {
                        continue;
                    }

                    $lease->overdue_since = $date;
                    $lease->saveQuietly();

                    if ($date === null) {
                        $cleared++;
                    } else {
                        $flagged++;
                    }
                }
            });

        $this->info("Overdue flags refreshed: {$flagged} flagged, {$cleared} cleared.");

        return self::SUCCESS;
    }
}

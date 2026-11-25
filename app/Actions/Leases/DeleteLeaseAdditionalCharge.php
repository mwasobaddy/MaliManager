<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\User;
use App\Services\Service;

/**
 * Removes a lease extra. Password confirmation lives on the request; the
 * action only checks the lease still owns the row.
 */
class DeleteLeaseAdditionalCharge extends Service
{
    public function handle(LeaseAdditionalCharge $extra, Lease $lease, User $actor): void
    {
        abort_unless($extra->lease_id === $lease->id, 404);

        $this->transaction(function () use ($extra): void {
            $extra->delete();
        });
    }
}

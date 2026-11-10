<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
use App\Services\Service;

class TerminateLeaseOccupant extends Service
{
    use OccupantHelpers;

    public function handle(Lease $lease): void
    {
        $this->transaction(function () use ($lease) {
            $lease->update([
                'ends_at' => now(),
                'status' => 'ended',
            ]);

            if ($lease->unit_id !== null) {
                $this->refreshUnitStatus($lease->unit_id);
            }

            $this->stripOccupantRoleIfNoActiveLeases($lease->person);
        });
    }
}

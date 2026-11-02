<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Occupant;
use App\Services\Service;

class DeleteOccupant extends Service
{
    use OccupantHelpers;

    public function handle(Occupant $occupant): void
    {
        $this->transaction(function () use ($occupant) {
            $unitIds = $occupant->units()->pluck('units.id')->all();

            foreach ($unitIds as $unitId) {
                $this->endLease($occupant, $unitId);
            }

            $occupant->units()->detach();

            $this->stripOccupantRoleIfNoActiveLeases($occupant->person);

            $this->delete($occupant);
        });
    }
}

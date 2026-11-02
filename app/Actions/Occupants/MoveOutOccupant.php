<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Occupant;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

class MoveOutOccupant extends Service
{
    use OccupantHelpers;

    public function handle(Occupant $occupant, Property $property, User $actor): void
    {
        $this->transaction(function () use ($occupant, $property) {
            $unitIds = $occupant->units()
                ->where('units.property_id', $property->id)
                ->pluck('units.id')
                ->all();

            foreach ($unitIds as $unitId) {
                $this->endLease($occupant, $unitId);
            }

            $occupant->units()
                ->where('units.property_id', $property->id)
                ->detach();

            $this->stripOccupantRoleIfNoActiveLeases($occupant->person);

            $occupant->fill(['status' => 'moved_out'])->save();
        });
    }
}

<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

class CreateOccupant extends Service
{
    use OccupantHelpers;

    public function handle(Organization $organization, Property $property, User $actor, array $data): Occupant
    {
        return $this->transaction(function () use ($organization, $property, $actor, $data) {
            $user = $this->findOrCreateUser($data);

            $person = $this->findOrCreatePerson($data, $user, $actor);

            $occupant = Occupant::withTrashed()->firstOrNew([
                'organization_id' => $organization->id,
                'person_id' => $person->id,
            ]);

            $occupant->fill([
                'status' => $data['status'] ?? 'active',
                'created_by' => $actor->id,
            ]);

            $occupant->deleted_at = null;
            $occupant->save();

            $this->syncUnitAssignments($occupant, $property, $data['unit_ids'] ?? [], $actor, $data['lease'] ?? []);

            return $occupant;
        });
    }
}

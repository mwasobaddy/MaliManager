<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Occupant;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

class UpdateOccupant extends Service
{
    use OccupantHelpers;

    public function handle(Occupant $occupant, Property $property, User $actor, array $data): Occupant
    {
        return $this->transaction(function () use ($occupant, $property, $actor, $data) {
            $person = $occupant->person;

            $person->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
            ])->save();

            $this->findOrCreateUser($data);

            $occupant->fill([
                'status' => $data['status'] ?? 'active',
            ])->save();

            $this->syncUnitAssignments($occupant, $property, $data['unit_ids'] ?? [], $actor, $data['lease'] ?? []);

            return $occupant;
        });
    }
}

<?php

namespace App\Actions\Occupants;

use App\Actions\Leases\CreateLeaseAdditionalCharge;
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

            // Move-in now (the default) activates immediately and records
            // the deposit plus first-period rent; otherwise this is a
            // booking — inactive occupant with pending leases, activated
            // later through the move-in step.
            $moveIn = (bool) ($data['move_in_now'] ?? true);

            $occupant->fill([
                'status' => $moveIn ? 'active' : 'inactive',
                'created_by' => $actor->id,
            ]);

            $occupant->deleted_at = null;
            $occupant->save();

            $created = $this->syncUnitAssignments(
                $occupant,
                $property,
                $data['unit_ids'] ?? [],
                $actor,
                $data['lease'] ?? [],
                $moveIn ? 'active' : 'pending',
            );

            // Move-in extras land on every lease created above (multi-unit
            // move-ins bill each tenancy the same fees). Currency falls
            // back to the lease currency inside the action, so the tenant
            // sees these in the agreement before signing.
            foreach ($created as $lease) {
                foreach ($data['lease']['extras'] ?? [] as $extra) {
                    app(CreateLeaseAdditionalCharge::class)->handle($actor, $organization, $lease, [
                        'name' => $extra['name'],
                        'kind' => $extra['kind'],
                        'amount' => $extra['amount'],
                    ]);
                }
            }

            if ($moveIn) {
                $this->recordMoveInPayments($occupant, $property, $actor, $created, $data);
            }

            return $occupant;
        });
    }
}

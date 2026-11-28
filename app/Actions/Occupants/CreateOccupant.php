<?php

namespace App\Actions\Occupants;

use App\Actions\Leases\CreateLeaseAdditionalCharge;
use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Service;

class CreateOccupant extends Service
{
    use OccupantHelpers;

    /**
     * One occupant row per unit: the same person renting several units
     * gets several records sharing the person (and the login, the contact
     * details, the rental history). An already-assigned unit reuses its
     * active row instead of duplicating it, so retries and double submits
     * stay idempotent. Returns the first row for callers that only need
     * the person link.
     */
    public function handle(Organization $organization, Property $property, User $actor, array $data): Occupant
    {
        return $this->transaction(function () use ($organization, $property, $actor, $data) {
            $user = $this->findOrCreateUser($data);

            $person = $this->findOrCreatePerson($data, $user, $actor);

            // Move-in now (the default) activates immediately and records
            // the deposit plus first-period rent; otherwise this is a
            // booking — inactive occupant with pending leases, activated
            // later through the move-in step.
            $moveIn = (bool) ($data['move_in_now'] ?? true);

            $unitIds = array_values(array_unique(array_map('intval', (array) ($data['unit_ids'] ?? []))));

            $validIds = Unit::where('property_id', $property->id)
                ->whereIn('id', $unitIds)
                ->pluck('id')
                ->all();

            $created = [];
            $rows = [];

            if ($validIds === []) {
                // No units: a prospect record with no tenancy yet. Reuse
                // the person's unit-less row if there is one.
                $prospect = Occupant::query()
                    ->where('organization_id', $organization->id)
                    ->where('person_id', $person->id)
                    ->where('status', '!=', 'moved_out')
                    ->whereDoesntHave('units', fn ($query) => $query->where('units.property_id', $property->id))
                    ->orderBy('id')
                    ->first();

                if (! $prospect) {
                    $prospect = new Occupant([
                        'organization_id' => $organization->id,
                        'person_id' => $person->id,
                        'status' => 'inactive',
                        'created_by' => $actor->id,
                    ]);
                } else {
                    $prospect->fill([
                        'status' => 'inactive',
                        'created_by' => $actor->id,
                    ]);
                }

                $prospect->deleted_at = null;
                $prospect->save();
                $rows[] = $prospect;
            }

            foreach ($validIds as $unitId) {
                $occupant = $this->resolveRowForUnit($organization, $person, $property, $unitId, $actor, $moveIn);
                $occupant->save();

                $created[] = $this->syncUnitAssignments(
                    $occupant,
                    $property,
                    [$unitId],
                    $actor,
                    $data['lease'] ?? [],
                    $moveIn ? 'active' : 'pending',
                );

                $rows[] = $occupant;
            }

            $created = array_merge(...$created);

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
                $first = $rows[0] ?? null;

                if ($first) {
                    $this->recordMoveInPayments($first, $property, $actor, $created, $data);
                }
            }

            return $rows[0];
        });
    }
}

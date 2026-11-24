<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
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
                'gender' => $data['gender'] ?? null,
            ])->save();

            $this->findOrCreateUser($data);

            $requestedStatus = $data['status'] ?? $occupant->status;

            $occupant->fill([
                'status' => $requestedStatus,
            ])->save();

            // New units follow the requested state: an active occupant signs
            // active leases, anything else books pending ones for a later
            // move-in. Validation below reads the end state, so a single edit
            // can both rotate units and resolve the status truthfully.
            $this->syncUnitAssignments(
                $occupant,
                $property,
                $data['unit_ids'] ?? [],
                $actor,
                $data['lease'] ?? [],
                $requestedStatus === 'active' ? 'active' : 'pending',
            );

            // Status is a record of the leasing truth, not a free label:
            // moving out with live leases would orphan them, and going
            // active or staying inactive with a live lease invents or hides
            // a tenancy. Those go through the move-in / move-out steps.
            $hasActive = $this->hasActiveLeases($occupant->person_id);

            if ($requestedStatus === 'moved_out' && $hasActive) {
                abort(422, 'This occupant still holds active leases — move them out instead of editing the status.');
            }

            if ($requestedStatus === 'active' && ! $hasActive) {
                abort(422, 'There is no active lease to back this status — move the occupant in first.');
            }

            if ($requestedStatus === 'inactive' && $hasActive) {
                abort(422, 'This occupant still holds active leases — end them before deactivating.');
            }

            if ($requestedStatus === 'moved_out') {
                $this->cancelPendingLeases($occupant, $property);
            }

            return $occupant;
        });
    }

    private function hasActiveLeases(int $personId): bool
    {
        return Lease::query()
            ->where('person_id', $personId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();
    }
}

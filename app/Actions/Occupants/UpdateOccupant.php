<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Models\Unit;
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
                'email' => $data['email'] ?? $person->email,
                'phone' => $data['phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'gender' => $data['gender'] ?? null,
            ])->save();

            // Completing the profile: the account link is made the first
            // time an email is known, never before.
            if (! empty($data['email'])) {
                $user = $this->findOrCreateUser($data);

                if (! $user->person_id) {
                    $user->fill(['person_id' => $person->id])->save();
                }
            }

            // One row per tenancy: the requested units are the person's
            // whole set in this property. Added units get their own row
            // (and lease); removed ones end and their row moves out.
            $requestedIds = array_map('intval', array_unique((array) ($data['unit_ids'] ?? [])));
            $rowsByUnit = $this->rowsByUnit($occupant, $property);
            $currentIds = array_keys($rowsByUnit);

            $requestedStatus = $data['status'] ?? $occupant->status;
            $leaseStatus = $requestedStatus === 'active' ? 'active' : 'pending';

            foreach (array_diff($requestedIds, $currentIds) as $addedId) {
                $unit = Unit::where('property_id', $property->id)->whereKey($addedId)->first();

                if (! $unit) {
                    continue;
                }

                $row = $this->resolveRowForUnit($property->organization, $person, $property, $addedId, $actor, $requestedStatus === 'active');
                $row->save();

                $this->syncUnitAssignments($row, $property, [$addedId], $actor, $data['lease'] ?? [], $leaseStatus);
            }

            foreach (array_diff($currentIds, $requestedIds) as $removedId) {
                $this->endLease($occupant, $removedId);
                $this->cancelPendingLeases($occupant, $property, $removedId);

                // The holder row loses the unit for good: detach it so the
                // row no longer claims the tenancy. The edited row's own
                // fate is decided below; siblings move out outright.
                $holder = $rowsByUnit[$removedId] ?? null;

                if ($holder instanceof Occupant) {
                    $holder->units()->detach($removedId);

                    if ($holder->id !== $occupant->id) {
                        $holder->fill(['status' => 'moved_out'])->save();
                    }
                }
            }

            $this->stripOccupantRoleIfNoActiveLeases($person);

            // The edited row follows its own lease truth: its units are the
            // ones it still holds after this edit.
            $rowUnitIds = $occupant->units()->where('units.property_id', $property->id)->pluck('units.id')->all();
            $rowHasActive = ! empty($rowUnitIds) && Lease::query()
                ->where('person_id', $person->id)
                ->whereIn('unit_id', $rowUnitIds)
                ->where('status', 'active')
                ->whereNull('ends_at')
                ->exists();

            if (empty($rowUnitIds)) {
                // Vacating the row is a move-out by another name when the
                // row held a real tenancy; a unit-less row with no history
                // simply takes the requested status (never active — there
                // is no lease to back it).
                $rowHadTenancy = Lease::query()
                    ->where('person_id', $person->id)
                    ->where('property_id', $property->id)
                    ->where('occupant_id', $occupant->id)
                    ->exists();

                if ($rowHadTenancy) {
                    $occupant->fill(['status' => 'moved_out'])->save();
                } else {
                    abort_if($requestedStatus === 'active', 422, 'There is no active lease to back this status — move the occupant in first.');

                    $occupant->fill(['status' => $requestedStatus])->save();
                }
            } else {
                // Status is a record of the leasing truth, not a free
                // label: moving out with a live lease would orphan it, and
                // going active or staying inactive with a live lease invents
                // or hides a tenancy. Those go through the move-in /
                // move-out steps.
                if ($requestedStatus === 'moved_out' && $rowHasActive) {
                    abort(422, 'This occupant still holds active leases — move them out instead of editing the status.');
                }

                if ($requestedStatus === 'active' && ! $rowHasActive) {
                    abort(422, 'There is no active lease to back this status — move the occupant in first.');
                }

                if ($requestedStatus === 'inactive' && $rowHasActive) {
                    abort(422, 'This occupant still holds active leases — end it before deactivating.');
                }

                $occupant->fill(['status' => $requestedStatus])->save();
            }

            return $occupant;
        });
    }

    /**
     * Every live row of this person in the property, keyed by the unit it
     * currently holds. One row holds at most one unit.
     *
     * @return array<int, Occupant>
     */
    private function rowsByUnit(Occupant $occupant, Property $property): array
    {
        $rows = Occupant::query()
            ->where('organization_id', $property->organization_id)
            ->where('person_id', $occupant->person_id)
            ->with(['units' => fn ($query) => $query->where('units.property_id', $property->id)])
            ->get();

        $byUnit = [];

        foreach ($rows as $row) {
            foreach ($row->units as $unit) {
                $byUnit[$unit->id] ??= $row;
            }
        }

        return $byUnit;
    }
}

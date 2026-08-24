<?php

namespace App\Services;

use App\Enums\PlatformRole;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenancyContext;
use Illuminate\Support\Collection;

/**
 * Manages occupants within an organization. An occupant links a
 * global Person (and its User account) to one or more units inside
 * a single property. All writes are atomic.
 */
class OccupantService extends Service
{
    /**
     * Create (or re-activate) an occupant for a person and assign units.
     */
    public function create(Organization $organization, Property $property, User $actor, array $data): Occupant
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

    /**
     * Update an occupant's identity, status, and unit assignments.
     */
    public function update(Occupant $occupant, Property $property, User $actor, array $data): Occupant
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

    /**
     * Soft-delete an occupant and detach all their units. The linked
     * Person/User are kept since they are global identities.
     */
    public function softDelete(Occupant $occupant): void
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

    /**
     * Move an occupant out of every unit in the given property. Their active
     * leases are ended (preserving rental history) and the `occupant` role is
     * stripped if they no longer hold any active lease. The occupant record is
     * kept (status `moved_out`) so the person can be re-added later.
     */
    public function moveOut(Occupant $occupant, Property $property, User $actor): void
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

    /**
     * The units of a property that an occupant is assigned to.
     *
     * @return Collection<int, Unit>
     */
    public function unitsForProperty(Occupant $occupant, Property $property): Collection
    {
        return $occupant->units()
            ->where('units.property_id', $property->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find the user account for an occupant, creating it if needed.
     */
    private function findOrCreateUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $user->fill([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'phone' => $data['phone'] ?? $user->phone,
                'onboarded_at' => $user->onboarded_at ?? now(),
            ])->save();

            $user->assignRole(PlatformRole::Occupant->value);

            return $user;
        }

        $user = new User([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'onboarded_at' => now(),
            'status' => 'active',
        ]);

        $user = $this->save($user);
        $user->assignRole(PlatformRole::Occupant->value);

        return $user;
    }

    /**
     * Find (by email) or create the global Person record.
     */
    private function findOrCreatePerson(array $data, User $user, User $actor): Person
    {
        $person = Person::where('email', $data['email'])->first();

        if (! $person) {
            $person = new Person([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $person = $this->save($person);
        } else {
            $person->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? $person->phone,
                'national_id' => $data['national_id'] ?? $person->national_id,
            ])->save();
        }

        if (! $user->person_id) {
            $user->fill(['person_id' => $person->id])->save();
        }

        return $person;
    }

    /**
     * Sync an occupant's unit assignments to the units selected,
     * constrained to the given property. Creates a Lease for each newly
     * assigned unit and ends the Lease for any unit the occupant leaves.
     * When the person no longer holds any active lease, their `occupant`
     * platform role is stripped (they revert to a `searcher`).
     *
     * @param  array<string, mixed>  $leaseData
     */
    private function syncUnitAssignments(Occupant $occupant, Property $property, array $unitIds, User $actor, array $leaseData = []): void
    {
        $unitIds = array_map('intval', array_unique($unitIds));

        $validIds = Unit::where('property_id', $property->id)
            ->whereIn('id', $unitIds)
            ->pluck('id')
            ->all();

        $currentIds = $occupant->units()
            ->where('units.property_id', $property->id)
            ->pluck('units.id')
            ->all();

        foreach (array_diff($currentIds, $validIds) as $detachedId) {
            $this->endLease($occupant, $detachedId);
        }

        $occupant->units()
            ->where('units.property_id', $property->id)
            ->detach();

        foreach ($validIds as $unitId) {
            $occupant->units()->attach($unitId, ['created_by' => $actor->id]);
            $this->ensureLease($occupant, $property, $unitId, $actor, $leaseData);
        }

        $this->stripOccupantRoleIfNoActiveLeases($occupant->person);
    }

    /**
     * End any active lease for this person on the given unit.
     */
    private function endLease(Occupant $occupant, int $unitId): void
    {
        Lease::where('person_id', $occupant->person_id)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->update([
                'ends_at' => now(),
                'status' => 'ended',
            ]);
    }

    /**
     * Create a lease for a newly assigned unit, unless one is already active.
     *
     * @param  array<string, mixed>  $leaseData
     */
    private function ensureLease(Occupant $occupant, Property $property, int $unitId, User $actor, array $leaseData): void
    {
        $active = Lease::where('person_id', $occupant->person_id)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        if ($active) {
            return;
        }

        $lease = new Lease([
            'person_id' => $occupant->person_id,
            'organization_id' => $property->organization_id,
            'tenant_id' => TenancyContext::tenantId(),
            'property_id' => $property->id,
            'unit_id' => $unitId,
            'occupant_id' => $occupant->id,
            'starts_at' => $leaseData['starts_at'] ?? now(),
            'rent_amount' => $leaseData['rent_amount'] ?? null,
            'rent_frequency' => $leaseData['rent_frequency'] ?? null,
            'currency' => $leaseData['currency'] ?? null,
            'deposit' => $leaseData['deposit'] ?? null,
            'agreement_text' => $leaseData['agreement_text'] ?? null,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        $lease->save();
    }

    /**
     * If the person has no active lease anywhere, remove the `occupant`
     * platform role from any linked user accounts.
     */
    private function stripOccupantRoleIfNoActiveLeases(Person $person): void
    {
        $hasActive = Lease::where('person_id', $person->id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        if ($hasActive) {
            return;
        }

        User::where('person_id', $person->id)
            ->whereHas('roles', fn ($query) => $query->where('name', PlatformRole::Occupant->value))
            ->get()
            ->each(fn (User $user) => $user->removeRole(PlatformRole::Occupant->value));
    }
}

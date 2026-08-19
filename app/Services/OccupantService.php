<?php

namespace App\Services;

use App\Enums\PlatformRole;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
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

            $this->syncUnitAssignments($occupant, $property, $data['unit_ids'] ?? [], $actor);

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

            $this->syncUnitAssignments($occupant, $property, $data['unit_ids'] ?? [], $actor);

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
            $occupant->units()->detach();

            $this->delete($occupant);
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
     * constrained to the given property.
     */
    private function syncUnitAssignments(Occupant $occupant, Property $property, array $unitIds, User $actor): void
    {
        $unitIds = array_map('intval', array_unique($unitIds));

        $validIds = Unit::where('property_id', $property->id)
            ->whereIn('id', $unitIds)
            ->pluck('id')
            ->all();

        $occupant->units()
            ->where('units.property_id', $property->id)
            ->detach();

        foreach ($validIds as $unitId) {
            $occupant->units()->attach($unitId, ['created_by' => $actor->id]);
        }
    }
}

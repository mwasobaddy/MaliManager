<?php

namespace App\Services;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Actions\Occupants\CreateOccupant;
use App\Actions\Occupants\DeleteOccupant;
use App\Actions\Occupants\MoveInOccupant;
use App\Actions\Occupants\MoveOutOccupant;
use App\Actions\Occupants\TerminateLeaseOccupant;
use App\Actions\Occupants\UpdateOccupant;
use App\Models\Lease;
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
    use OccupantHelpers;

    /**
     * Create (or re-activate) an occupant for a person and assign units.
     */
    public function create(Organization $organization, Property $property, User $actor, array $data): Occupant
    {
        return app(CreateOccupant::class)->handle($organization, $property, $actor, $data);
    }

    /**
     * Update an occupant's identity, status, and unit assignments.
     */
    public function update(Occupant $occupant, Property $property, User $actor, array $data): Occupant
    {
        return app(UpdateOccupant::class)->handle($occupant, $property, $actor, $data);
    }

    /**
     * Soft-delete an occupant and detach all their units. The linked
     * Person/User are kept since they are global identities.
     */
    public function softDelete(Occupant $occupant): void
    {
        app(DeleteOccupant::class)->handle($occupant);
    }

    /**
     * Move an occupant out of every unit in the given property. Their active
     * leases are ended (preserving rental history) and the `occupant` role is
     * stripped if they no longer hold any active lease. The occupant record is
     * kept (status `moved_out`) so the person can be re-added later.
     */
    public function moveOut(Occupant $occupant, Property $property, User $actor): void
    {
        app(MoveOutOccupant::class)->handle($occupant, $property, $actor);
    }

    /**
     * Move a booked occupant in: pending leases in the property activate on
     * the move-in date once the deposit and first-period rent are recorded.
     *
     * @param  array<string, mixed>  $data
     */
    public function moveIn(Occupant $occupant, Property $property, User $actor, array $data): Occupant
    {
        return app(MoveInOccupant::class)->handle($occupant, $property, $actor, $data);
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
     * Terminate a single lease early: stamp the end date, mark it ended,
     * and strip the occupant platform role if no active leases remain.
     * The actor is threaded through so ending the lease can start the
     * deposit refund in the same breath.
     */
    public function terminateLease(Lease $lease, ?User $actor = null): void
    {
        app(TerminateLeaseOccupant::class)->handle($lease, $actor);
    }
}

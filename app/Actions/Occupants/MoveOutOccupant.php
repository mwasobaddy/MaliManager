<?php

namespace App\Actions\Occupants;

use App\Actions\Deposits\AutoRequestDepositRefund;
use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

class MoveOutOccupant extends Service
{
    use OccupantHelpers;

    public function __construct(private AutoRequestDepositRefund $autoRequest) {}

    /**
     * Vacate one tenancy row: its leases end, its bookings cancel, its
     * units free up and the row moves out. Every ended lease holding a
     * deposit files its refund, exactly like an explicit lease
     * termination. Other rows of the same person are untouched — each
     * tenancy moves out on its own. The occupant role falls off only when
     * no active lease remains anywhere for the person.
     */
    public function handle(Occupant $occupant, Property $property, User $actor): void
    {
        $this->transaction(function () use ($occupant, $property, $actor) {
            // Snapshot the tenancies this move-out ends: each one holding a
            // deposit gets its refund auto-filed below, exactly like an
            // explicit lease termination.
            $leaseIds = Lease::where('occupant_id', $occupant->id)
                ->where('property_id', $property->id)
                ->where('status', 'active')
                ->whereNull('ends_at')
                ->pluck('id')
                ->all();

            $unitIds = $occupant->units()
                ->where('units.property_id', $property->id)
                ->pluck('units.id')
                ->all();

            foreach ($unitIds as $unitId) {
                $this->endLease($occupant, $unitId);
                $this->cancelPendingLeases($occupant, $property, $unitId);
            }

            $occupant->units()
                ->where('units.property_id', $property->id)
                ->detach();

            $this->stripOccupantRoleIfNoActiveLeases($occupant->person);

            $occupant->fill(['status' => 'moved_out'])->save();

            $organization = $property->organization;

            foreach (Lease::whereKey($leaseIds)->get() as $lease) {
                $this->autoRequest->handle($organization, $lease->refresh(), $actor, null, 'move-out');
            }
        });
    }
}

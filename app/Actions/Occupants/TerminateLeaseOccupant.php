<?php

namespace App\Actions\Occupants;

use App\Actions\Deposits\AutoRequestDepositRefund;
use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

class TerminateLeaseOccupant extends Service
{
    use OccupantHelpers;

    public function __construct(private AutoRequestDepositRefund $autoRequest) {}

    public function handle(Lease $lease, ?User $actor = null): void
    {
        $this->transaction(function () use ($lease, $actor) {
            $lease->update([
                'ends_at' => now(),
                'status' => 'ended',
            ]);

            if ($lease->unit_id !== null) {
                $this->refreshUnitStatus($lease->unit_id);
            }

            $this->stripOccupantRoleIfNoActiveLeases($lease->person);

            $occupant = $lease->occupant;

            if ($occupant !== null) {
                $this->refreshOccupantStatus($occupant);
            }

            // An ended tenancy with a held deposit starts its refund here,
            // in awaiting-inspection. Deliberately only on explicit
            // termination: unit reassignments end leases too, but the tenant
            // has not moved out then and auto-filing would be wrong.
            if ($actor !== null && $lease->organization_id !== null) {
                $organization = Organization::find($lease->organization_id);

                if ($organization !== null) {
                    $this->autoRequest->handle($organization, $lease->refresh(), $actor, null, 'termination');
                }
            }
        });
    }
}

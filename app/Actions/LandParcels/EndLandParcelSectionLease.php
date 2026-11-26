<?php

declare(strict_types=1);

namespace App\Actions\LandParcels;

use App\Actions\Deposits\AutoRequestDepositRefund;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\User;
use App\Services\Service;

/**
 * Ends a section lease early, mirroring unit-lease termination: stamp the
 * end date, mark it ended, free the section back to vacant, and start the
 * deposit refund when one was held.
 */
class EndLandParcelSectionLease extends Service
{
    public function __construct(private AutoRequestDepositRefund $autoRequest) {}

    public function handle(LandParcel $parcel, LandParcelSection $section, User $actor): Lease
    {
        abort_if($section->land_parcel_id !== $parcel->id, 404);

        $lease = $section->activeLease();
        abort_if($lease === null, 422, 'This section has no active lease to end.');

        return $this->transaction(function () use ($parcel, $section, $lease, $actor): Lease {
            $lease->update([
                'ends_at' => now(),
                'status' => 'ended',
            ]);

            $section->update(['status' => 'vacant']);

            if ($lease->organization_id !== null) {
                $this->autoRequest->handle($parcel->organization, $lease->refresh(), $actor, null, 'termination');
            }

            return $lease->refresh();
        });
    }
}

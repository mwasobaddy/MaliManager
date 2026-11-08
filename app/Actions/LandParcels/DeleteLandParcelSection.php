<?php

namespace App\Actions\LandParcels;

use App\Models\LandParcelSection;
use App\Services\Service;

/**
 * Removes one section of a land parcel.
 *
 * Sections carry no delegations of their own, so unlike the parcel delete this
 * is a single delete. The parcel/section match stays in the controller, where a
 * mismatch answers 404.
 */
class DeleteLandParcelSection extends Service
{
    public function handle(LandParcelSection $section): void
    {
        $this->transaction(function () use ($section): void {
            $this->delete($section);
        });
    }
}

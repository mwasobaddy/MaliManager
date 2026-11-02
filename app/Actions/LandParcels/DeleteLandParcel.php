<?php

namespace App\Actions\LandParcels;

use App\Actions\LandParcels\Concerns\LandParcelHelpers;
use App\Models\Delegation;
use App\Models\LandParcel;
use App\Services\Service;

class DeleteLandParcel extends Service
{
    use LandParcelHelpers;

    public function handle(LandParcel $parcel): void
    {
        $this->transaction(function () use ($parcel) {
            Delegation::where('delegatable_type', LandParcel::class)
                ->where('delegatable_id', $parcel->id)
                ->delete();

            $this->delete($parcel);
        });
    }
}

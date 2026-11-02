<?php

namespace App\Actions\LandParcels;

use App\Actions\LandParcels\Concerns\LandParcelHelpers;
use App\Models\LandParcel;
use App\Models\User;
use App\Services\Service;

class UpdateLandParcel extends Service
{
    use LandParcelHelpers;

    public function handle(LandParcel $parcel, User $actor, array $data): LandParcel
    {
        return $this->transaction(function () use ($parcel, $actor, $data) {
            $parcel->fill([
                'name' => $data['name'],
                'title_deed_number' => $data['title_deed_number'] ?? null,
                'acreage' => $data['acreage'] ?? null,
                'zoning' => $data['zoning'] ?? $parcel->zoning,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'status' => $data['status'] ?? $parcel->status,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'available_for_lease' => $data['available_for_lease'] ?? false,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->syncDelegations($parcel, $data['manager_ids'] ?? [], $actor);

            return $parcel;
        });
    }
}

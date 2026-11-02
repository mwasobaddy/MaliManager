<?php

namespace App\Actions\LandParcels;

use App\Actions\LandParcels\Concerns\LandParcelHelpers;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

class CreateLandParcel extends Service
{
    use LandParcelHelpers;

    public function handle(Organization $organization, User $actor, array $data): LandParcel
    {
        return $this->transaction(function () use ($organization, $actor, $data) {
            $parcel = $this->save(new LandParcel([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => LandParcel::uniqueSlug($organization->id, $data['name']),
                'title_deed_number' => $data['title_deed_number'] ?? null,
                'acreage' => $data['acreage'] ?? null,
                'zoning' => $data['zoning'] ?? 'residential',
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'status' => $data['status'] ?? 'active',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'available_for_lease' => $data['available_for_lease'] ?? false,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]));

            $this->syncDelegations($parcel, $data['manager_ids'] ?? [], $actor);

            return $parcel;
        });
    }
}

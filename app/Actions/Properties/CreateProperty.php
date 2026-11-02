<?php

namespace App\Actions\Properties;

use App\Actions\Properties\Concerns\PropertyHelpers;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyService;
use App\Services\Service;
use App\Support\StorageLayout;

class CreateProperty extends Service
{
    use PropertyHelpers;

    public function handle(Organization $organization, User $user, array $data): Property
    {
        $this->assertWithinLimit(
            $organization->plan?->properties_limit,
            $organization->properties()->count(),
            'property',
        );

        return $this->transaction(function () use ($organization, $user, $data) {
            $property = $this->save(new Property([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => Property::uniqueSlug($organization->id, $data['name']),
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'location_url' => $data['location_url'] ?? null,
                'property_type_id' => $data['property_type_id'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
            ]));

            StorageLayout::bootstrapProperty($property);

            foreach ($data['units'] ?? [] as $unit) {
                app(PropertyService::class)->addUnit($property, $user, $unit);
            }

            return $property;
        });
    }
}

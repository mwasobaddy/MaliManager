<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\StorageLayout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Creates properties and units, enforcing the organization's plan
 * limits for properties and units.
 */
class PropertyService extends Service
{
    public function create(Organization $organization, User $user, array $data): Property
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
                $this->addUnit($property, $user, $unit);
            }

            return $property;
        });
    }

    public function addUnit(Property $property, User $user, array $data): Unit
    {
        $this->assertWithinLimit(
            $property->organization->plan?->units_limit,
            $property->units()->count(),
            'unit',
        );

        return $this->transaction(function () use ($property, $user, $data) {
            $unit = $this->save(new Unit([
                'property_id' => $property->id,
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? null,
                'status' => 'vacant',
                'created_by' => $user->id,
            ]));

            $this->attachUnitPhotos($unit, $data['photos'] ?? []);

            return $unit;
        });
    }

    /**
     * Attach photos uploaded alongside the unit.
     *
     * Inside the same transaction as the unit insert, so a failed upload cannot
     * leave a unit whose photo set half-wrote. Medialibrary records its own
     * rows; `$unit` is saved above so it has an id for them to point at.
     *
     * @param  iterable<UploadedFile>  $photos
     */
    private function attachUnitPhotos(Unit $unit, iterable $photos): void
    {
        foreach ($photos as $photo) {
            $unit->addMedia($photo)->toMediaCollection('photos');
        }
    }

    private function assertWithinLimit(?int $limit, int $current, string $resource): void
    {
        if ($limit !== null && $current >= $limit) {
            throw new \DomainException(
                "Your current plan allows up to {$limit} ".Str::plural($resource, (int) $limit).'. Please upgrade your plan to add more.',
            );
        }
    }
}

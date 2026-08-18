<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

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
                'status' => 'active',
                'created_by' => $user->id,
            ]));

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
            return $this->save(new Unit([
                'property_id' => $property->id,
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? null,
                'status' => 'vacant',
                'created_by' => $user->id,
            ]));
        });
    }

    private function assertWithinLimit(?int $limit, int $current, string $resource): void
    {
        if ($limit !== null && $current >= $limit) {
            throw new \DomainException(
                "Your current plan allows up to {$limit} {$resource}s. Please upgrade your plan to add more.",
            );
        }
    }
}

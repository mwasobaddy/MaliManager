<?php

namespace App\Services;

use App\Actions\Properties\AddPropertyUnit;
use App\Actions\Properties\Concerns\PropertyHelpers;
use App\Actions\Properties\CreateProperty;
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
    use PropertyHelpers;

    public function create(Organization $organization, User $user, array $data): Property
    {
        return app(CreateProperty::class)->handle($organization, $user, $data);
    }

    public function addUnit(Property $property, User $user, array $data): Unit
    {
        return app(AddPropertyUnit::class)->handle($property, $user, $data);
    }
}

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

    /**
     * Every photo already stored on the organization's units, grouped by
     * unit for the reuse picker. Small enough to ship whole: photos are
     * capped per unit and portfolios are small.
     *
     * @return list<array{unit_id: int, unit_name: string, property_name: string, photos: list<array{id: int, url: string, name: string}>}>
     */
    public function photoLibrary(Organization $organization): array
    {
        $propertyIds = $organization->properties()->pluck('id');

        return Unit::query()
            ->whereIn('property_id', $propertyIds)
            ->whereHas('media', fn ($query) => $query->where('collection_name', 'photos'))
            ->with([
                'property:id,name',
                'media' => fn ($query) => $query->where('collection_name', 'photos')->orderBy('order_column'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit): array => [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'property_name' => $unit->property?->name ?? '',
                'photos' => $unit->photoPayload(),
            ])
            ->all();
    }
}

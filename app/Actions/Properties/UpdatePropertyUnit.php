<?php

namespace App\Actions\Properties;

use App\Actions\Properties\Concerns\PropertyHelpers;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Service;
use Illuminate\Support\Str;

/**
 * Applies an edit sheet to a unit: scalar fields, an optional move to
 * another property in the same organization, and appended photos.
 *
 * A move is refused while an active lease points at the unit — history
 * (ended leases keep their own property reference) is untouched, but a
 * live tenancy must be ended before its rooms change buildings. The slug
 * is re-derived on move because uniqueness is scoped to the property.
 */
class UpdatePropertyUnit extends Service
{
    use PropertyHelpers;

    public function handle(Unit $unit, array $data): Unit
    {
        return $this->transaction(function () use ($unit, $data) {
            if (array_key_exists('property_id', $data)
                && (int) $data['property_id'] !== (int) $unit->property_id
            ) {
                $this->moveToProperty(
                    $unit,
                    Property::query()->findOrFail((int) $data['property_id']),
                );
            }

            $unit->update([
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? null,
            ]);

            $this->attachAdditionalUnitPhotos($unit, $data);

            return $unit;
        });
    }

    private function moveToProperty(Unit $unit, Property $property): void
    {
        $activeLease = Lease::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();

        if ($activeLease) {
            throw new \DomainException(
                'A unit with an active lease cannot move properties. End the lease first.',
            );
        }

        $unit->property_id = $property->id;

        $base = Str::limit(Str::slug($unit->name) ?: 'unit', 100, '');
        $slug = $base;
        $suffix = 2;

        while (Unit::query()
            ->where('property_id', $property->id)
            ->where('id', '!=', $unit->id)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = Str::limit($base, 100 - strlen((string) $suffix), '').'-'.$suffix;
            $suffix++;
        }

        $unit->slug = $slug;
    }
}

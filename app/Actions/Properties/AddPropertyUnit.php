<?php

namespace App\Actions\Properties;

use App\Actions\Properties\Concerns\PropertyHelpers;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Service;

class AddPropertyUnit extends Service
{
    use PropertyHelpers;

    public function handle(Property $property, User $user, array $data): Unit
    {
        $this->assertWithinLimit(
            $property->organization->effectivePlan()?->units_limit,
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
}

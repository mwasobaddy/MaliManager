<?php

namespace App\Actions\Properties;

use App\Actions\Metering\AttachChargesToUnit;
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
                'rent_frequency' => $data['rent_frequency'] ?? 'monthly',
                'rent_amount' => $data['rent_amount'] ?? null,
                'status' => 'vacant',
                'created_by' => $user->id,
            ]));

            $this->attachAdditionalUnitPhotos($unit, $data);

            // Charges picked on the create sheet land in the same
            // transaction, so a bad assignment never leaves a bare unit
            // behind: validation rejects it before anything is saved.
            if (! empty($data['charges'])) {
                app(AttachChargesToUnit::class)->handle($user, $property->organization, $property, $unit, $data['charges']);
            }

            return $unit;
        });
    }
}

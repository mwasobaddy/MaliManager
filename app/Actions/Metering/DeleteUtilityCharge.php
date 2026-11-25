<?php

namespace App\Actions\Metering;

use App\Models\Property;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Services\Service;

/**
 * Removes a charge and its unit assignments. Readings are untouched — they
 * are raw meter facts, not billing rows — so nothing already measured is
 * lost.
 */
class DeleteUtilityCharge extends Service
{
    public function handle(UtilityCharge $charge, Property $property, User $actor): void
    {
        abort_unless($charge->property_id === $property->id, 404);

        $this->transaction(function () use ($charge) {
            $charge->units()->detach();
            $this->delete($charge);
        });
    }
}

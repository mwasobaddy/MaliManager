<?php

namespace App\Actions\Metering;

use App\Enums\UtilityChargeKind;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Services\Service;

/**
 * Assigns existing property charges to a unit. The mirror image of charge
 * creation: where CreateUtilityCharge assigns chosen units to a new charge,
 * this assigns chosen charges to a new unit, so the landlord picks water,
 * garbage and the rest once, at unit creation, instead of revisiting every
 * charge sheet afterwards.
 *
 * Fixed charges need a per-unit amount; metered and shared carry nothing on
 * the pivot. A brand-new unit has no readings, so the metered watermark
 * stays null and the first reading bills in full.
 */
class AttachChargesToUnit extends Service
{
    /**
     * @param  list<array{charge_id: int, amount?: numeric-string|float|int|null}>  $assignments
     */
    public function handle(User $actor, Organization $organization, Property $property, Unit $unit, array $assignments): void
    {
        if ($assignments === []) {
            return;
        }

        abort_unless($unit->property_id === $property->id, 404);

        $ids = array_values(array_unique(array_map(fn ($assignment): int => (int) $assignment['charge_id'], $assignments)));

        $charges = UtilityCharge::query()
            ->where('organization_id', $organization->id)
            ->where('property_id', $property->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        abort_unless($charges->count() === count($ids), 422, 'One of the chosen charges is not in this property.');

        foreach ($assignments as $assignment) {
            $charge = $charges[(int) $assignment['charge_id']];

            abort_unless(
                $charge->kind !== UtilityChargeKind::Fixed || (isset($assignment['amount']) && (float) $assignment['amount'] > 0),
                422,
                'Every fixed charge needs an amount.'
            );
        }

        $this->transaction(function () use ($unit, $charges, $assignments) {
            foreach ($assignments as $assignment) {
                $charge = $charges[(int) $assignment['charge_id']];

                $charge->units()->attach($unit->id, [
                    'amount' => $charge->kind === UtilityChargeKind::Fixed ? $assignment['amount'] : null,
                    'last_billed_reading_id' => null,
                ]);
            }
        });
    }
}

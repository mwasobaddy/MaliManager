<?php

namespace App\Actions\Metering;

use App\Enums\UtilityChargeKind;
use App\Enums\UtilityType;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

/**
 * Creates a utility charge and assigns it to units. Kind-specific
 * completeness lives here, where the units are known: metered needs a
 * utility and a rate, fixed needs an amount per unit.
 *
 * Metered assignments backfill the billing watermark to the latest reading,
 * so a new charge only ever bills consumption recorded after it exists.
 */
class CreateUtilityCharge extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, Property $property, array $data): UtilityCharge
    {
        abort_unless($property->organization_id === $organization->id, 404);

        $kind = UtilityChargeKind::tryFrom((string) ($data['kind'] ?? ''));
        abort_unless($kind !== null, 422, 'Unknown charge kind.');

        $utility = isset($data['utility']) && $data['utility'] !== null && $data['utility'] !== ''
            ? UtilityType::tryFrom((string) $data['utility'])
            : null;

        if ($kind === UtilityChargeKind::Metered) {
            abort_unless($utility !== null, 422, 'A metered charge needs a utility.');
            abort_unless(isset($data['rate']) && (float) $data['rate'] > 0, 422, 'A metered charge needs a rate per unit.');
        }

        if ($kind === UtilityChargeKind::Shared) {
            abort_unless(in_array($data['allocation'] ?? '', ['equal', 'occupants'], true), 422, 'A shared charge needs a split basis.');
            abort_unless(isset($data['master_amount']) && (float) $data['master_amount'] > 0, 422, 'A shared charge needs the master bill total.');
        }

        $unitIds = $this->resolveUnitIds($property, (array) ($data['unit_ids'] ?? []));

        if ($kind === UtilityChargeKind::Fixed) {
            foreach ($unitIds as $unitId) {
                abort_unless(
                    isset($data['amounts'][$unitId]) && (float) $data['amounts'][$unitId] > 0,
                    422,
                    'Every assigned unit needs an amount for a fixed charge.'
                );
            }
        }

        return $this->transaction(function () use ($actor, $organization, $property, $data, $kind, $utility, $unitIds) {
            $charge = new UtilityCharge([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'name' => $data['name'],
                'kind' => $kind->value,
                'utility' => $utility?->value,
                'rate' => $kind === UtilityChargeKind::Metered ? $data['rate'] : null,
                'allocation' => $kind === UtilityChargeKind::Shared ? $data['allocation'] : null,
                'master_amount' => $kind === UtilityChargeKind::Shared ? $data['master_amount'] : null,
                'currency' => $data['currency'] ?? 'KES',
                'gl_code' => $data['gl_code'] ?? null,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
            $this->save($charge);

            foreach ($unitIds as $unitId) {
                $charge->units()->attach($unitId, [
                    'amount' => $kind === UtilityChargeKind::Fixed
                        ? $data['amounts'][$unitId]
                        : null,
                    'last_billed_reading_id' => $kind === UtilityChargeKind::Metered && $utility !== null
                        ? $this->latestReadingId($unitId, $utility->value)
                        : null,
                ]);
            }

            return $charge;
        });
    }

    /**
     * @return list<int>
     */
    private function resolveUnitIds(Property $property, array $unitIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $unitIds)));

        abort_if($ids === [], 422, 'Choose at least one unit.');

        $valid = DB::table('units')
            ->where('property_id', $property->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(count($valid) === count($ids), 422, 'One of the chosen units is not in this property.');

        return $valid;
    }

    private function latestReadingId(int $unitId, string $utility): ?int
    {
        $id = UtilityReading::query()
            ->where('unit_id', $unitId)
            ->where('utility', $utility)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}

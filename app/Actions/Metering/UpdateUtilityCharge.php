<?php

namespace App\Actions\Metering;

use App\Enums\UtilityChargeKind;
use App\Enums\UtilityType;
use App\Models\Property;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

/**
 * Renames, reprices, toggles and reassigns a charge. The kind is immutable:
 * metered↔fixed would invalidate rates against per-unit amounts, so a
 * miscategorized charge is deleted and recreated instead of morphed.
 */
class UpdateUtilityCharge extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(UtilityCharge $charge, Property $property, User $actor, array $data): UtilityCharge
    {
        abort_unless($charge->property_id === $property->id, 404);

        if ($charge->kind === UtilityChargeKind::Metered) {
            $utility = isset($data['utility']) && $data['utility'] !== null && $data['utility'] !== ''
                ? UtilityType::tryFrom((string) $data['utility'])
                : null;
            abort_unless($utility !== null, 422, 'A metered charge needs a utility.');
            abort_unless(isset($data['rate']) && (float) $data['rate'] > 0, 422, 'A metered charge needs a rate per unit.');
        }

        if ($charge->kind === UtilityChargeKind::Shared) {
            abort_unless(in_array($data['allocation'] ?? $charge->allocation, ['equal', 'occupants'], true), 422, 'A shared charge needs a split basis.');
            abort_unless(isset($data['master_amount']) && (float) $data['master_amount'] > 0, 422, 'A shared charge needs the master bill total.');
        }

        $unitIds = $this->resolveUnitIds($property, (array) ($data['unit_ids'] ?? []));

        if (in_array($charge->kind, [UtilityChargeKind::Fixed, UtilityChargeKind::OneTime], true)) {
            foreach ($unitIds as $unitId) {
                abort_unless(
                    isset($data['amounts'][$unitId]) && (float) $data['amounts'][$unitId] > 0,
                    422,
                    'Every assigned unit needs an amount for a fixed or one-time charge.'
                );
            }
        }

        return $this->transaction(function () use ($charge, $data, $unitIds) {
            $charge->fill([
                'name' => $data['name'],
                'utility' => $charge->kind === UtilityChargeKind::Metered ? UtilityType::tryFrom((string) ($data['utility'] ?? ''))?->value : $charge->utility,
                'rate' => $charge->kind === UtilityChargeKind::Metered ? $data['rate'] : null,
                'allocation' => $charge->kind === UtilityChargeKind::Shared ? ($data['allocation'] ?? $charge->allocation) : null,
                'master_amount' => $charge->kind === UtilityChargeKind::Shared ? $data['master_amount'] : null,
                'currency' => $data['currency'] ?? $charge->currency,
                'gl_code' => $data['gl_code'] ?? $charge->gl_code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $charge->is_active,
            ])->save();

            $current = $charge->units()->pluck('units.id')->map(fn ($id) => (int) $id)->all();

            $charge->units()->detach(array_diff($current, $unitIds));

            foreach (array_diff($unitIds, $current) as $unitId) {
                $charge->units()->attach($unitId, [
                    'amount' => in_array($charge->kind, [UtilityChargeKind::Fixed, UtilityChargeKind::OneTime], true)
                        ? $data['amounts'][$unitId]
                        : null,
                    'last_billed_reading_id' => $charge->kind === UtilityChargeKind::Metered && $charge->utility !== null
                        ? $this->latestReadingId($unitId, $charge->utility->value)
                        : null,
                ]);
            }

            foreach (array_intersect($unitIds, $current) as $unitId) {
                if (in_array($charge->kind, [UtilityChargeKind::Fixed, UtilityChargeKind::OneTime], true)) {
                    $charge->units()->updateExistingPivot($unitId, ['amount' => $data['amounts'][$unitId]]);
                }
            }

            return $charge->refresh();
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

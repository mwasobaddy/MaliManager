<?php

namespace App\Support;

use App\Enums\UtilityChargeKind;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
use Illuminate\Support\Collection;

/**
 * Turns utility charges into payable figures.
 *
 * Metered lines bill consumption *since the last billed reading* at the
 * charge rate; fixed lines bill the per-unit amount; pending one-time
 * lines list at face value until Phase B invoices consume them. Everything
 * is computed — nothing is stored — so the numbers always reflect the
 * current readings and the current charge setup.
 *
 * Currency is taken from the lease; a charge in another currency is
 * skipped rather than silently converted (there is no FX source).
 */
final class UtilityBilling
{
    /**
     * @return array{lines: list<array{charge_id: int, name: string, kind: string, detail: string, amount: string, last_reading: string|null, last_read_on: string|null}>, metered: string, fixed: string, one_time: string, total: string}
     */
    public static function breakdownForLease(Lease $lease): array
    {
        if ($lease->unit_id === null) {
            return self::empty();
        }

        $charges = UtilityCharge::query()
            ->where('property_id', $lease->property_id)
            ->where('is_active', true)
            ->where('currency', $lease->currency ?? 'KES')
            ->whereHas('units', fn ($query) => $query->where('units.id', $lease->unit_id))
            ->with(['units' => fn ($query) => $query->where('units.id', $lease->unit_id)])
            ->get();

        $lines = [];
        $metered = '0.00';
        $fixed = '0.00';
        $oneTime = '0.00';

        foreach ($charges as $charge) {
            $pivot = $charge->units->first()?->pivot;

            if ($pivot === null) {
                continue;
            }

            if ($charge->kind === UtilityChargeKind::Metered && $charge->utility !== null) {
                $unbilled = self::unbilledConsumption($lease->unit_id, $charge->utility->value, $pivot->last_billed_reading_id);
                $rate = rtrim(rtrim((string) ($charge->rate ?? 0), '0'), '.');
                $amount = bcmul($unbilled['consumption'], (string) ($charge->rate ?? 0), 2);
                $lines[] = [
                    'charge_id' => $charge->id,
                    'name' => $charge->name,
                    'kind' => $charge->kind->value,
                    'detail' => "{$unbilled['consumption']} {$charge->utility->unitOfMeasure()} × {$rate}",
                    'amount' => $amount,
                    'last_reading' => $unbilled['last_reading'] !== null ? "{$unbilled['last_reading']} {$charge->utility->unitOfMeasure()}" : null,
                    'last_read_on' => $unbilled['last_read_on'],
                ];
                $metered = bcadd($metered, $amount, 2);

                continue;
            }

            if ($charge->kind === UtilityChargeKind::Fixed && $pivot->amount !== null) {
                $amount = number_format((float) $pivot->amount, 2, '.', '');
                $lines[] = [
                    'charge_id' => $charge->id,
                    'name' => $charge->name,
                    'kind' => $charge->kind->value,
                    'detail' => 'per period',
                    'amount' => $amount,
                    'last_reading' => null,
                    'last_read_on' => null,
                ];
                $fixed = bcadd($fixed, $amount, 2);

                continue;
            }

            if ($charge->kind === UtilityChargeKind::OneTime && $pivot->amount !== null && $pivot->consumed_at === null) {
                $amount = number_format((float) $pivot->amount, 2, '.', '');
                $lines[] = [
                    'charge_id' => $charge->id,
                    'name' => $charge->name,
                    'kind' => $charge->kind->value,
                    'detail' => 'one-time',
                    'amount' => $amount,
                    'last_reading' => null,
                    'last_read_on' => null,
                ];
                $oneTime = bcadd($oneTime, $amount, 2);
            }
        }

        return [
            'lines' => $lines,
            'metered' => $metered,
            'fixed' => $fixed,
            'one_time' => $oneTime,
            'total' => bcadd(bcadd((string) ($lease->rent_amount ?? 0), $metered, 2), bcadd($fixed, $oneTime, 2), 2),
        ];
    }

    /**
     * Page-level totals for a batch of leases: one charge query and one
     * latest-readings query regardless of row count, so the table never
     * pays per-row.
     *
     * @param  Collection<int, Lease>  $leases
     * @return array<int, array{metered: string, fixed: string, one_time: string, total: string}>
     */
    public static function totalsForLeases(Collection $leases): array
    {
        $result = [];

        foreach ($leases->groupBy('property_id') as $leasesInProperty) {
            $propertyId = (int) $leasesInProperty->first()->property_id;
            $currency = (string) ($leasesInProperty->first()->currency ?? 'KES');

            $charges = UtilityCharge::query()
                ->where('property_id', $propertyId)
                ->where('is_active', true)
                ->where('currency', $currency)
                ->with(['units' => fn ($query) => $query->whereIn(
                    'units.id',
                    $leasesInProperty->map(fn (Lease $lease) => $lease->unit_id)->filter()->all()
                )])
                ->get();

            $byUnit = [];
            foreach ($charges as $charge) {
                foreach ($charge->units as $unit) {
                    $byUnit[$unit->id][] = ['charge' => $charge, 'pivot' => $unit->pivot];
                }
            }

            $readings = self::latestReadings(
                $leasesInProperty->map(fn (Lease $lease) => $lease->unit_id)->filter()->all(),
                $charges->where('kind', UtilityChargeKind::Metered)->map(fn ($charge) => $charge->utility->value)->filter()->unique()->all()
            );

            foreach ($leasesInProperty as $lease) {
                $metered = '0.00';
                $fixed = '0.00';
                $oneTime = '0.00';

                foreach ($byUnit[$lease->unit_id] ?? [] as ['charge' => $charge, 'pivot' => $pivot]) {
                    if ($charge->kind === UtilityChargeKind::Metered && $charge->utility !== null) {
                        $unbilled = self::unbilledFromReading(
                            $readings[$lease->unit_id.'|'.$charge->utility->value] ?? null,
                            $pivot->last_billed_reading_id,
                            $charge->utility->value
                        );
                        $metered = bcadd($metered, bcmul($unbilled['consumption'], (string) ($charge->rate ?? 0), 2), 2);
                    } elseif ($charge->kind === UtilityChargeKind::Fixed && $pivot->amount !== null) {
                        $fixed = bcadd($fixed, number_format((float) $pivot->amount, 2, '.', ''), 2);
                    } elseif ($charge->kind === UtilityChargeKind::OneTime && $pivot->amount !== null && $pivot->consumed_at === null) {
                        $oneTime = bcadd($oneTime, number_format((float) $pivot->amount, 2, '.', ''), 2);
                    }
                }

                $result[$lease->id] = [
                    'metered' => $metered,
                    'fixed' => $fixed,
                    'one_time' => $oneTime,
                    'total' => bcadd(bcadd((string) ($lease->rent_amount ?? 0), $metered, 2), bcadd($fixed, $oneTime, 2), 2),
                ];
            }
        }

        return $result;
    }

    /**
     * Unbilled consumption for one unit+utility: latest reading minus the
     * last billed reading. A null marker means nothing was ever billed, so
     * everything on record counts — new charges backfill the marker at
     * setup instead, so this path only sees legacy gaps.
     *
     * @return array{consumption: string, last_reading: string|null, last_read_on: string|null}
     */
    private static function unbilledConsumption(int $unitId, string $utility, mixed $lastBilledReadingId): array
    {
        $latest = UtilityReading::query()
            ->where('unit_id', $unitId)
            ->where('utility', $utility)
            ->orderByDesc('id')
            ->first();

        return self::unbilledFromReading($latest, $lastBilledReadingId, $utility);
    }

    /**
     * @return array{consumption: string, last_reading: string|null, last_read_on: string|null}
     */
    private static function unbilledFromReading(?UtilityReading $latest, mixed $lastBilledReadingId, string $utility): array
    {
        if ($latest === null) {
            return ['consumption' => '0.00', 'last_reading' => null, 'last_read_on' => null];
        }

        $billed = null;
        if ($lastBilledReadingId !== null) {
            $billed = UtilityReading::query()->whereKey($lastBilledReadingId)->first();
        }

        $consumption = $billed === null
            ? (string) ($latest->consumption ?? $latest->reading ?? 0)
            : bcsub((string) $latest->reading, (string) $billed->reading, 3);

        if (bccomp($consumption, '0', 3) < 0) {
            $consumption = '0.00';
        }

        return [
            'consumption' => number_format((float) $consumption, 3, '.', ''),
            'last_reading' => (string) $latest->reading,
            'last_read_on' => $latest->read_on?->toDateString(),
        ];
    }

    /**
     * Latest reading per unit+utility pair in one query, keyed
     * `"{unit_id}|{utility}"`. Bounded by pair count, never by history.
     *
     * @param  list<int>  $unitIds
     * @param  list<string>  $utilities
     * @return array<string, UtilityReading>
     */
    private static function latestReadings(array $unitIds, array $utilities): array
    {
        if ($unitIds === [] || $utilities === []) {
            return [];
        }

        $ids = UtilityReading::query()
            ->whereIn('unit_id', array_values(array_unique($unitIds)))
            ->whereIn('utility', array_values(array_unique($utilities)))
            ->groupBy('unit_id', 'utility')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        return UtilityReading::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (UtilityReading $reading) => $reading->unit_id.'|'.$reading->utility->value)
            ->all();
    }

    /**
     * @return array{lines: list<array{charge_id: int, name: string, kind: string, detail: string, amount: string, last_reading: string|null, last_read_on: string|null}>, metered: string, fixed: string, one_time: string, total: string}
     */
    private static function empty(): array
    {
        return ['lines' => [], 'metered' => '0.00', 'fixed' => '0.00', 'one_time' => '0.00', 'total' => '0.00'];
    }
}

<?php

namespace App\Support;

use App\Enums\UtilityChargeKind;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * @return array{lines: list<array{charge_id: int, name: string, kind: string, detail: string, amount: string, last_reading: string|null, last_read_on: string|null}>, metered: string, fixed: string, one_time: string, shared: string, total: string}
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
        $shared = '0.00';

        $sharedCharges = $charges->where('kind', UtilityChargeKind::Shared);
        $sharedShares = self::sharesForCharges($sharedCharges, $lease->property_id);


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

                continue;
            }

            if ($charge->kind === UtilityChargeKind::Shared) {
                $share = $sharedShares[$charge->id] ?? null;
                $amount = $share['units'][(int) $lease->unit_id] ?? null;

                if ($share === null || $amount === null) {
                    continue;
                }

                $lines[] = [
                    'charge_id' => $charge->id,
                    'name' => $charge->name,
                    'kind' => $charge->kind->value,
                    'detail' => $share['detail'],
                    'amount' => $amount,
                    'last_reading' => null,
                    'last_read_on' => null,
                ];
                $shared = bcadd($shared, $amount, 2);
            }
        }

        return [
            'lines' => $lines,
            'metered' => $metered,
            'fixed' => $fixed,
            'one_time' => $oneTime,
            'shared' => $shared,
            'total' => bcadd(bcadd(bcadd((string) ($lease->rent_amount ?? 0), $metered, 2), bcadd($fixed, $oneTime, 2), 2), $shared, 2),
        ];
    }

    /**
     * Page-level totals for a batch of leases: one charge query and one
     * latest-readings query regardless of row count, so the table never
     * pays per-row.
     *
     * @param  Collection<int, Lease>  $leases
     * @return array<int, array{metered: string, fixed: string, one_time: string, shared: string, total: string}>
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

            $sharedCharges = $charges->where('kind', UtilityChargeKind::Shared);
            $sharedShares = self::sharesForCharges($sharedCharges, $propertyId);


            $readings = self::latestReadings(
                $leasesInProperty->map(fn (Lease $lease) => $lease->unit_id)->filter()->all(),
                $charges->where('kind', UtilityChargeKind::Metered)->map(fn ($charge) => $charge->utility->value)->filter()->unique()->all()
            );

            foreach ($leasesInProperty as $lease) {
                $metered = '0.00';
                $fixed = '0.00';
                $oneTime = '0.00';
                $shared = '0.00';

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
                    } elseif ($charge->kind === UtilityChargeKind::Shared) {
                        $share = $sharedShares[$charge->id] ?? null;
                        $amount = $share['units'][(int) $lease->unit_id] ?? '0.00';
                        $shared = bcadd($shared, $amount, 2);
                    }
                }

                $result[$lease->id] = [
                    'metered' => $metered,
                    'fixed' => $fixed,
                    'one_time' => $oneTime,
                    'shared' => $shared,
                    'total' => bcadd(bcadd(bcadd((string) ($lease->rent_amount ?? 0), $metered, 2), bcadd($fixed, $oneTime, 2), 2), $shared, 2),
                ];
            }
        }

        return $result;
    }

    /**
     * Per-charge shares for a batch of shared (RUBS) charges: one pivot
     * query and one active-lease count regardless of charge count.
     *
     * @param  Collection<int, UtilityCharge>  $sharedCharges
     * @return array<int, array{units: array<int, string>, detail: string}>
     */
    private static function sharesForCharges(Collection $sharedCharges, int $propertyId): array
    {
        if ($sharedCharges->isEmpty()) {
            return [];
        }

        $chargeIds = $sharedCharges->map(fn (UtilityCharge $charge) => $charge->id)->all();

        $unitIdsByCharge = [];
        foreach (DB::table('utility_charge_unit')->whereIn('utility_charge_id', $chargeIds)->get(['utility_charge_id', 'unit_id']) as $row) {
            $unitIdsByCharge[(int) $row->utility_charge_id][] = (int) $row->unit_id;
        }

        $allUnitIds = array_values(array_unique(array_merge(...array_values($unitIdsByCharge ?: [[]]))));

        $counts = $allUnitIds === []
            ? []
            : Lease::query()->active()
                ->where('property_id', $propertyId)
                ->whereIn('unit_id', $allUnitIds)
                ->groupBy('unit_id')
                ->selectRaw('unit_id, COUNT(*) as c')
                ->pluck('c', 'unit_id')
                ->map(fn ($c) => (int) $c)
                ->all();

        $result = [];
        foreach ($sharedCharges as $charge) {
            $unitIds = $unitIdsByCharge[$charge->id] ?? [];

            if ($unitIds === []) {
                continue;
            }

            $weights = [];
            foreach ($unitIds as $unitId) {
                $weights[$unitId] = $charge->allocation === 'occupants'
                    ? (int) ($counts[$unitId] ?? 0)
                    : 1;
            }

            $basis = 'units';
            if ($charge->allocation === 'occupants' && array_sum($weights) > 0) {
                $basis = 'occupants';
            } elseif ($charge->allocation === 'occupants') {
                // All vacant: split equally so the master bill still ties.
                $weights = array_fill_keys($unitIds, 1);
            }

            $divisor = $basis === 'occupants' ? array_sum($weights) : count($unitIds);
            $master = number_format((float) ($charge->master_amount ?? 0), 2, '.', '');

            $result[$charge->id] = [
                'units' => self::sharedShares($master, $weights),
                'detail' => "shared master {$master} {$charge->currency} ÷ {$divisor} {$basis}",
            ];
        }

        return $result;
    }

    /**
     * Splits a master bill across weighted units with largest-remainder,
     * so the lines tie to the master total to the cent instead of leaking
     * a cent to rounding on every period.
     *
     * @param  array<int, int>  $weights  unit id => weight
     * @return array<int, string> unit id => amount
     */
    public static function sharedShares(string $masterAmount, array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0 || bccomp($masterAmount, '0', 2) <= 0) {
            return array_map(fn () => '0.00', $weights);
        }

        $totalCents = (int) round((float) $masterAmount * 100);
        $floors = [];
        $fractions = [];
        foreach ($weights as $unitId => $weight) {
            $exact = $totalCents * $weight / $total;
            $floors[$unitId] = (int) floor($exact);
            $fractions[$unitId] = $exact - $floors[$unitId];
        }

        $remainder = $totalCents - array_sum($floors);
        arsort($fractions);
        foreach (array_keys($fractions) as $unitId) {
            if ($remainder <= 0) {
                break;
            }
            $floors[$unitId]++;
            $remainder--;
        }

        return array_map(fn (int $cents) => number_format($cents / 100, 2, '.', ''), $floors);
    }

    /**
     * Days in a billing period for a frequency, from a reference date
     * inside it. Daily is one day, weekly seven, yearly the year's length;
     * monthly (and anything unknown) the calendar month's length.
     */
    public static function periodDays(string $frequency, ?CarbonImmutable $reference = null): int
    {
        $ref = $reference ?? now()->toImmutable();

        return match ($frequency) {
            'daily' => 1,
            'weekly' => 7,
            'yearly' => $ref->isLeapYear() ? 366 : 365,
            default => $ref->daysInMonth,
        };
    }

    /**
     * Pro-rates a fixed full-period amount by days occupied. Period length
     * comes from the frequency; occupancy is the overlap of the occupied
     * window with the period, clamped to the period. A full-period stay
     * returns the face amount with a `per period` label.
     *
     * Display math only for now: Phase B invoices will call this per line.
     *
     * @return array{amount: string, days_occupied: int, days_in_period: int, full: bool, label: string}
     */
    public static function prorate(string $amount, string $frequency, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, CarbonImmutable $occupiedFrom, ?CarbonImmutable $occupiedTo = null): array
    {
        $period = self::periodDays($frequency, $periodStart);
        $occupiedTo ??= $periodEnd;

        $from = $occupiedFrom->greaterThan($periodStart) ? $occupiedFrom : $periodStart;
        $to = $occupiedTo->lessThan($periodEnd) ? $occupiedTo : $periodEnd;

        // Inclusive day counts: a same-day move-in/move-out still bills a day.
        $occupied = $to->lessThan($from) ? 0 : (int) ($from->diffInDays($to) + 1);
        $occupied = min(max($occupied, 0), $period);

        if ($occupied >= $period) {
            return [
                'amount' => number_format((float) $amount, 2, '.', ''),
                'days_occupied' => $occupied,
                'days_in_period' => $period,
                'full' => true,
                'label' => 'per period',
            ];
        }

        $prorated = number_format(round((float) $amount * $occupied / $period, 2), 2, '.', '');

        return [
            'amount' => $prorated,
            'days_occupied' => $occupied,
            'days_in_period' => $period,
            'full' => false,
            'label' => "prorated {$occupied} of {$period} days",
        ];
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


    /**
     * @return array{lines: list<array{charge_id: int, name: string, kind: string, detail: string, amount: string, last_reading: string|null, last_read_on: string|null}>, metered: string, fixed: string, one_time: string, shared: string, total: string}
     */
    private static function empty(): array
    {
        return ['lines' => [], 'metered' => '0.00', 'fixed' => '0.00', 'one_time' => '0.00', 'shared' => '0.00', 'total' => '0.00'];
    }
}

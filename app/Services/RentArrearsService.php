<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Organization;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rent arrears: expected-vs-paid per active lease. Only `received`
 * payments count — pending and refunded never reduce a balance, and the
 * M-Pesa/card integration will write `received` automatically when it
 * lands, so no new status is needed for gateway money.
 *
 * Money stays decimal throughout (bcmath): the lease casts rent as
 * float, so it is normalized to a 2dp string at the boundary.
 */
class RentArrearsService extends Service
{
    /**
     * Balances for the given active leases, keyed by lease id. One
     * payments aggregate covers the whole batch — no per-lease queries.
     *
     * @param  Collection<int, Lease>  $leases
     * @return array<int, array{lease: Lease, expected: string, paid: string, outstanding: string, overdue: bool, days_overdue: int, latest_due_date: ?string}>
     */
    public function balancesFor(Collection $leases): array
    {
        if ($leases->isEmpty()) {
            return [];
        }

        $paidByLease = Payment::query()
            ->whereIn('lease_id', $leases->modelKeys())
            ->where('status', 'received')
            ->groupBy('lease_id')
            ->selectRaw('lease_id, SUM(amount) as total')
            ->pluck('total', 'lease_id');

        $balances = [];

        foreach ($leases as $lease) {
            $balances[$lease->id] = $this->balanceFor($lease, (string) ($paidByLease->get($lease->id) ?? '0.00'));
        }

        return $balances;
    }

    /**
     * Balance for a single lease (tenant dashboard, reminders). The paid
     * total can be supplied by the caller; otherwise it is queried.
     *
     * @return array{lease: Lease, expected: string, paid: string, outstanding: string, overdue: bool, days_overdue: int, latest_due_date: ?string}
     */
    public function balanceFor(Lease $lease, ?string $paid = null): array
    {
        $paid ??= (string) $lease->payments()->where('status', 'received')->sum('amount');

        $rent = number_format((float) ($lease->rent_amount ?? 0), 2, '.', '');
        $dueDates = $this->dueDates($lease);

        $expected = bcmul($rent, (string) count($dueDates), 2);
        $outstanding = bcsub($expected, number_format((float) $paid, 2, '.', ''), 2);
        // Only active leases can be overdue: ended ones keep their final
        // balance for display, but nothing is due anymore.
        $overdue = $lease->isActive() && bccomp($outstanding, '0', 2) === 1 && $dueDates !== [];

        $latest = $dueDates === [] ? null : end($dueDates);

        return [
            'lease' => $lease,
            'expected' => $expected,
            'paid' => number_format((float) $paid, 2, '.', ''),
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'days_overdue' => $overdue && $latest !== null ? (int) $latest->diffInDays(now()) : 0,
            'latest_due_date' => $latest?->toDateString(),
        ];
    }

    /**
     * Ids of overdue leases, optionally restricted to properties (staff
     * see only delegated ones). Drives the leases index overdue filter.
     *
     * @return array<int>
     */
    public function overdueLeaseIds(Organization $organization, ?array $propertyIds = null): array
    {
        $leases = Lease::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->when($propertyIds !== null, fn ($query) => $query->whereIn('property_id', $propertyIds))
            ->get();

        return collect($this->balancesFor($leases))
            ->filter(fn (array $balance): bool => $balance['overdue'])
            ->keys()
            ->all();
    }

    /**
     * Due dates on or before today, oldest first. Monthly (and missing
     * frequency) uses calendar months with the landlord's due day clamped
     * to the month length; other frequencies step whole periods from the
     * start date. A month whose due date predates the lease start is not
     * a period of this lease.
     *
     * @return list<CarbonImmutable>
     */
    private function dueDates(Lease $lease): array
    {
        if ($lease->starts_at === null) {
            return [];
        }

        $today = now()->toImmutable()->startOfDay();

        // A lease stops accruing at its end: periods on or past the end
        // date belong to nobody.
        $end = $lease->ends_at !== null
            ? $lease->ends_at->toImmutable()->startOfDay()
            : null;

        if ($end !== null && $end->lessThan($today)) {
            $today = $end;
        }
        $start = $lease->starts_at->toImmutable()->startOfDay();

        if ($start->greaterThan($today)) {
            return [];
        }

        if (($lease->rent_frequency ?? 'monthly') === 'monthly') {
            return $this->monthlyDueDates($lease, $start, $today, $end);
        }

        $length = match ($lease->rent_frequency) {
            'daily' => 1,
            'weekly' => 7,
            'yearly' => 365,
            default => 30,
        };

        $dates = [];
        $periodStart = $start;

        while (! $periodStart->greaterThan($today) && ($end === null || $periodStart->lessThan($end))) {
            $dates[] = $periodStart;
            $periodStart = $periodStart->addDays($length);
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function monthlyDueDates(Lease $lease, CarbonImmutable $start, CarbonImmutable $today, ?CarbonImmutable $end): array
    {
        $dates = [];
        $cursor = $start->startOfMonth();
        $last = $today->startOfMonth();

        while (! $cursor->greaterThan($last)) {
            $day = min($lease->dueDay(), $cursor->daysInMonth);
            $due = $cursor->setDay($day);

            if (! $due->greaterThan($today) && ! $due->lessThan($start) && ($end === null || $due->lessThan($end))) {
                $dates[] = $due;
            }

            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $dates;
    }
}

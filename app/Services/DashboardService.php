<?php

namespace App\Services;

use App\Enums\PlatformPermissionKey;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\PlatformExpense;
use App\Models\Property;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the central dashboard payload. Each section is only included when
 * the authenticated user holds the matching central permission, so the
 * frontend can safely render (and gate) tabs without leaking data the
 * user is not allowed to see.
 *
 * Real data is used where it exists (organization/plan/lease counts); the
 * expense and maintenance graphs are intentionally sample/placeholder
 * series until those models are introduced.
 */
class DashboardService extends Service
{
    /**
     * @param  array<int, array{id: int, properties: array<int, array{units_count: int}}>]|null  $access
     *         Precomputed PropertyAccessService result, when the caller already has it.
     */
    public function payload(User $user, ?array $access = null, ?int $year = null, ?int $month = null): array
    {
        $access ??= app(PropertyAccessService::class)->organizationsWithProperties($user);
        $year ??= (int) date('Y');

        return [
            'available_years' => $this->availableYears(),
            'admin' => $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value)
                ? $this->adminStats($year, $month)
                : null,
            'organization' => $user->can(PlatformPermissionKey::ViewOrgMetrics->value)
                ? $this->organizationStats($access, $year, $month)
                : null,
            'searcher' => $user->can(PlatformPermissionKey::ViewSearcherMetrics->value)
                ? $this->personLeaseStats($user->person_id, 'searcher', $year, $month)
                : null,
            'occupant' => $user->can(PlatformPermissionKey::ViewOccupantMetrics->value)
                ? $this->personLeaseStats($user->person_id, 'occupant', $year, $month)
                : null,
            'defaultTab' => $this->defaultTab($user),
        ];
    }

    /**
     * Distinct years that appear in any financial source plus the current
     * year, oldest first. Drives the dashboard year dropdown.
     *
     * @return list<int>
     */
    private function availableYears(): array
    {
        return collect()
            ->concat(SubscriptionPayment::query()->withoutTrashed()->pluck('received_on'))
            ->concat(PlatformExpense::query()->withoutTrashed()->pluck('spent_on'))
            ->push(now())
            ->map(fn ($value) => (int) value($value)->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function defaultTab(User $user): string
    {
        return match (true) {
            $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value) => 'admin',
            $user->can(PlatformPermissionKey::ViewOrgMetrics->value) => 'organization',
            $user->can(PlatformPermissionKey::ViewSearcherMetrics->value) => 'searcher',
            default => 'occupant',
        };
    }

    private function adminStats(int $year, ?int $month): array
    {
        $labels = $this->axisLabels($year, $month);

        $plansBreakdown = DB::table('organizations')
            ->whereNotNull('plan_id')
            ->join('plans', 'plans.id', '=', 'organizations.plan_id')
            ->select('plans.name as plan', DB::raw('count(*) as count'))
            ->groupBy('plans.name')
            ->get()
            ->map(fn (object $row) => ['plan' => $row->plan, 'count' => (int) $row->count])
            ->all();

        $paidByWindow = function ($column) use ($year, $month): callable {
            return function ($query) use ($column, $year, $month): void {
                $query->whereYear($column, $year);
                if ($month) {
                    $query->whereMonth($column, $month);
                }
            };
        };

        $incomeTotal = (float) SubscriptionPayment::query()
            ->where(fn ($q) => $paidByWindow('received_on')($q))
            ->sum('amount');

        $expenseTotal = (float) PlatformExpense::query()
            ->where(fn ($q) => $paidByWindow('spent_on')($q))
            ->sum('amount');

        $incomeSeries = [];
        $incomeQuery = SubscriptionPayment::query()->whereYear('received_on', $year);
        if ($month) {
            $incomeQuery->whereMonth('received_on', $month);
        }
        foreach ($incomeQuery->cursor(['received_on', 'amount']) as $payment) {
            $key = $this->formatKey($payment->received_on, $month);
            $incomeSeries[$key] = ($incomeSeries[$key] ?? 0) + (float) $payment->amount;
        }

        $expenseSeries = [];
        $expenseQuery = PlatformExpense::query()->whereYear('spent_on', $year);
        if ($month) {
            $expenseQuery->whereMonth('spent_on', $month);
        }
        foreach ($expenseQuery->cursor(['spent_on', 'amount']) as $expense) {
            $key = $this->formatKey($expense->spent_on, $month);
            $expenseSeries[$key] = ($expenseSeries[$key] ?? 0) + (float) $expense->amount;
        }

        $financials = $this->combineAxis($labels, [
            'income' => $incomeSeries,
            'expenses' => $expenseSeries,
        ]);

        return [
            'organizations_count' => Organization::count(),
            'subscribers_count' => Organization::whereNotNull('plan_id')->count(),
            'plans_count' => DB::table('plans')->count(),
            'income_total' => $incomeTotal,
            'expenses_total' => $expenseTotal,
            'net_total' => $incomeTotal - $expenseTotal,
            'plans_breakdown' => $plansBreakdown,
            'financials_monthly' => $financials,
        ];
    }

    /**
     * @param  array<int, array{id: int, properties: array<int, array{units_count: int}>}>  $access
     */
    private function organizationStats(array $access, int $year, ?int $month): array
    {
        $orgIds = collect($access)->pluck('id')->all();
        $properties = collect($access)->flatMap(fn (array $organization) => $organization['properties']);

        $labels = $this->axisLabels($year, $month);

        $expenseSeries = [];
        $maintenanceSeries = [];

        if ($orgIds) {
            foreach (Expense::query()->whereIn('organization_id', $orgIds)->whereYear('spent_on', $year)->cursor(['spent_on', 'amount']) as $expense) {
                $key = $this->formatKey($expense->spent_on, $month);
                $expenseSeries[$key] = ($expenseSeries[$key] ?? 0) + (float) $expense->amount;
            }

            foreach (MaintenanceRequest::query()->whereIn('organization_id', $orgIds)->whereYear('created_at', $year)->cursor(['created_at']) as $request) {
                $key = $this->formatKey($request->created_at, $month);
                $maintenanceSeries[$key] = ($maintenanceSeries[$key] ?? 0) + 1;
            }
        }

        $operations = $this->combineAxis($labels, [
            'expenses' => $expenseSeries,
            'maintenance' => $maintenanceSeries,
        ]);

        return [
            'properties_count' => $properties->count(),
            'units_count' => $properties->sum('units_count'),
            'leases_count' => $orgIds ? Lease::whereIn('organization_id', $orgIds)->count() : 0,
            'active_leases' => $orgIds ? Lease::whereIn('organization_id', $orgIds)->active()->count() : 0,
            'expenses_ytd' => $orgIds ? (float) Expense::query()->whereIn('organization_id', $orgIds)->whereYear('spent_on', $year)->sum('amount') : 0,
            'maintenance_open' => $orgIds
                ? MaintenanceRequest::query()->whereIn('organization_id', $orgIds)->whereIn('status', ['opened', 'assigned', 'in_progress'])->count()
                : 0,
            'operations_monthly' => $operations,
            'flags' => $this->predictiveFlags($orgIds, $properties),
            'expiring_leases' => $this->expiringLeases($orgIds),
        ];
    }

    /**
     * Active leases ending within 60 days (max 5), for the dashboard card.
     *
     * @param  array<int, int>  $orgIds
     * @return array<int, array{unit: string|null, occupant: string|null, ends_at: string, days_left: int}>
     */
    private function expiringLeases(array $orgIds): array
    {
        return Lease::query()
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->with(['unit:id,name', 'person:id,first_name,last_name'])
            ->orderBy('ends_at')
            ->limit(5)
            ->get()
            ->map(fn (Lease $lease): array => [
                'unit' => $lease->unit?->name,
                'occupant' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
                'ends_at' => $lease->ends_at?->toDateString(),
                'days_left' => now()->startOfDay()->diffInDays($lease->ends_at->startOfDay()),
            ])
            ->all();
    }

    /**
     * Deterministic predictive flags: expense outliers per asset/category
     * and stale urgent maintenance. The AI layer (R1 drafting/assistant)
     * can explain these; detection itself stays rule-based.
     *
     * @param  array<int, int>  $orgIds
     * @param  iterable<int, array{id: int, name: string}>  $properties
     * @return array<int, array{severity: string, message: string}>
     */
    private function predictiveFlags(array $orgIds, iterable $properties): array
    {
        if ($orgIds === []) {
            return [];
        }

        $flags = [];

        // Stale urgent maintenance: urgent requests opened more than 7 days
        // ago and still not resolved.
        $staleUrgent = MaintenanceRequest::query()
            ->whereIn('organization_id', $orgIds)
            ->where('priority', 'urgent')
            ->whereIn('status', ['opened', 'assigned', 'in_progress'])
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        if ($staleUrgent > 0) {
            $flags[] = [
                'severity' => 'high',
                'message' => "{$staleUrgent} urgent maintenance request".($staleUrgent > 1 ? 's have' : ' has').' been open for over a week.',
            ];
        }

        // Expense outliers: latest expense per asset+category deviating >2x
        // the average of prior expenses for that asset+category.
        $assets = collect($properties)->pluck('id')->all();

        $recent = Expense::query()
            ->where('organization_id', $orgIds[0])
            ->where('expenseable_type', Property::class)
            ->whereIn('expenseable_id', $assets)
            ->orderByDesc('spent_on')
            ->limit(50)
            ->get(['expenseable_id', 'category', 'amount', 'spent_on']);

        foreach ($recent as $expense) {
            $average = (float) Expense::query()
                ->where('organization_id', $orgIds[0])
                ->where('expenseable_type', Property::class)
                ->where('expenseable_id', $expense->expenseable_id)
                ->where('category', $expense->category)
                ->whereKeyNot($expense->id)
                ->avg('amount');

            if ($average > 0 && (float) $expense->amount > $average * 2) {
                $flags[] = [
                    'severity' => 'medium',
                    'message' => ucfirst((string) $expense->category).' expense of '.number_format((float) $expense->amount, 2)
                        .' is more than double the usual for this asset.',
                ];

                if (count($flags) >= 5) {
                    break;
                }
            }
        }

        return $flags;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personLeaseStats(?int $personId, string $kind, int $year, ?int $month): ?array
    {
        $labels = $this->axisLabels($year, $month);

        if (! $personId) {
            return [
                'leases_count' => 0,
                'active_leases' => 0,
                'ended_leases' => 0,
                'total_rent' => 0,
                'recent' => [],
            ] + ($kind === 'occupant' ? [
                'maintenance_open' => 0,
                'home_monthly' => $this->combineAxis($labels, []),
            ] : []);
        }

        $base = Lease::query()->where('person_id', $personId);

        // One conditional-aggregation pass instead of four separate scans.
        $totals = (clone $base)
            ->selectRaw('count(*) as leases_count')
            ->selectRaw("sum(case when status = 'active' and ends_at is null then 1 else 0 end) as active_leases")
            ->selectRaw("sum(case when status = 'ended' or ends_at is not null then 1 else 0 end) as ended_leases")
            ->selectRaw('coalesce(sum(rent_amount), 0) as total_rent')
            ->first();

        $recent = (clone $base)
            ->with(['organization', 'property'])
            ->latest('starts_at')
            ->take(5)
            ->get()
            ->map(fn (Lease $lease) => [
                'organization' => $lease->organization?->name,
                'property' => $lease->property?->name,
                'status' => $lease->status,
                'starts_at' => $lease->starts_at?->toDateString(),
                'ends_at' => $lease->ends_at?->toDateString(),
            ])
            ->all();

        $result = [
            'leases_count' => (int) ($totals->leases_count ?? 0),
            'active_leases' => (int) ($totals->active_leases ?? 0),
            'ended_leases' => (int) ($totals->ended_leases ?? 0),
            'total_rent' => (float) ($totals->total_rent ?? 0),
            'recent' => $recent,
        ];

        if ($kind !== 'occupant') {
            return $result;
        }

        $leaseIds = (clone $base)->pluck('id')->all();

        $rentSeries = [];
        $rentQuery = (clone $base)->whereYear('starts_at', $year);
        if ($month) {
            $rentQuery->whereMonth('starts_at', $month);
        }
        foreach ($rentQuery->cursor(['starts_at', 'rent_amount']) as $lease) {
            $key = $this->formatKey($lease->starts_at, $month);
            $rentSeries[$key] = ($rentSeries[$key] ?? 0) + (float) $lease->rent_amount;
        }

        $maintenanceSeries = [];
        if ($leaseIds) {
            $maintQuery = MaintenanceRequest::query()->whereIn('lease_id', $leaseIds)->whereYear('created_at', $year);
            if ($month) {
                $maintQuery->whereMonth('created_at', $month);
            }
            foreach ($maintQuery->cursor(['created_at']) as $request) {
                $key = $this->formatKey($request->created_at, $month);
                $maintenanceSeries[$key] = ($maintenanceSeries[$key] ?? 0) + 1;
            }
        }

        $result['maintenance_open'] = $leaseIds
            ? MaintenanceRequest::query()->whereIn('lease_id', $leaseIds)->whereIn('status', ['opened', 'assigned', 'in_progress'])->count()
            : 0;

        $result['home_monthly'] = $this->combineAxis($labels, [
            'rent' => $rentSeries,
            'maintenance' => $maintenanceSeries,
        ]);

        return $result;
    }

    /**
     * Build the per-property dashboard payload. Everything here is real data
     * scoped to a single property: unit composition, rent potential, open
     * maintenance, active leases, and monthly activity (rent roll, maintenance
     * requests, new leases).
     *
     * @return array<string, mixed>
     */
    public function propertyStats(Property $property): array
    {
        $now = now();

        // Last 6 month start-of-month points + short labels for the charts.
        $monthPoints = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthPoints[] = $now->copy()->subMonths($i)->startOfMonth();
        }
        $months = collect($monthPoints)->map(fn ($date) => $date->format('M'))->all();

        // Unit composition.
        $units = $property->units()->get(['status', 'monthly_rent']);
        $totalUnits = $units->count();
        $occupied = $units->where('status', 'occupied')->count();
        $vacant = $units->where('status', 'vacant')->count();
        $inMaintenance = $units->where('status', 'maintenance')->count();
        $rentPotential = (float) $units->sum(fn ($unit) => (float) $unit->monthly_rent);
        $occupancyRate = $totalUnits > 0 ? (int) round($occupied / $totalUnits * 100) : 0;

        $unitStatus = [
            ['status' => 'occupied', 'count' => $occupied, 'color' => '#22c55e'],
            ['status' => 'vacant', 'count' => $vacant, 'color' => '#eab308'],
            ['status' => 'maintenance', 'count' => $inMaintenance, 'color' => '#f97316'],
        ];

        $openMaintenance = MaintenanceRequest::query()
            ->where('property_id', $property->id)
            ->whereIn('status', ['opened', 'assigned', 'in_progress'])
            ->count();

        $activeLeases = Lease::query()
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->count();

        // Monthly counts, grouped in PHP (SQLite-safe, no DATE_FORMAT).
        $maintenanceMonthly = [];
        foreach (MaintenanceRequest::query()
            ->where('property_id', $property->id)
            ->where('created_at', '>=', $monthPoints[0])
            ->cursor(['created_at']) as $request) {
            $month = $request->created_at->format('M');
            $maintenanceMonthly[$month] = ($maintenanceMonthly[$month] ?? 0) + 1;
        }

        $newLeasesMonthly = [];
        foreach (Lease::query()
            ->where('property_id', $property->id)
            ->where('starts_at', '>=', $monthPoints[0])
            ->cursor(['starts_at']) as $lease) {
            $month = $lease->starts_at->format('M');
            $newLeasesMonthly[$month] = ($newLeasesMonthly[$month] ?? 0) + 1;
        }

        // Rent roll: sum of rent_amount for leases active during each month.
        $leases = Lease::query()
            ->where('property_id', $property->id)
            ->where(function ($query) use ($monthPoints): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $monthPoints[0]);
            })
            ->get(['starts_at', 'ends_at', 'rent_amount'])
            ->all();

        $rentRollMonthly = [];
        foreach ($monthPoints as $monthStart) {
            $start = $monthStart->copy()->startOfMonth();
            $end = $monthStart->copy()->endOfMonth();
            $roll = 0;

            foreach ($leases as $lease) {
                $leaseStart = $lease->starts_at;
                $leaseEnd = $lease->ends_at;

                if ($leaseStart && $leaseStart->lte($end)
                    && (is_null($leaseEnd) || $leaseEnd->gte($start))) {
                    $roll += (float) $lease->rent_amount;
                }
            }

            $rentRollMonthly[$monthStart->format('M')] = $roll;
        }

        $operations = $this->combineMonths($months, null, [
            'rent_roll' => $rentRollMonthly,
            'maintenance' => $maintenanceMonthly,
            'new_leases' => $newLeasesMonthly,
        ]);

        return [
            'total_units' => $totalUnits,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'in_maintenance' => $inMaintenance,
            'occupancy_rate' => $occupancyRate,
            'rent_potential' => $rentPotential,
            'open_maintenance' => $openMaintenance,
            'active_leases' => $activeLeases,
            'unit_status' => $unitStatus,
            'operations_monthly' => $operations,
        ];
    }

    /**
     * Generate the x-axis labels: month abbreviations when no month is
     * selected, or day numbers (1–28/29/30/31) when a specific month is chosen.
     *
     * @return list<string>
     */
    private function axisLabels(int $year, ?int $month): array
    {
        if ($month === null) {
            return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }

        $daysInMonth = (int) Carbon::createFromDate($year, $month, 1)->daysInMonth;

        return array_map(fn (int $day) => (string) $day, range(1, $daysInMonth));
    }

    /**
     * Return the date-format key used to group a timestamp into the correct
     * bucket on the current axis. 'M' for months (Jan, Feb…), 'j' for days (1, 2…).
     */
    private function formatKey(Carbon $date, ?int $month): string
    {
        return $month === null ? $date->format('M') : $date->format('j');
    }

    /**
     * Merge several key→value maps onto a fixed x-axis so they can be
     * plotted as multiple series on one chart. Missing points are 0.
     *
     * @param  list<string>             $axisLabels
     * @param  array<string, array<string, float|int>>  $series
     * @return array<int, array<string, float|int|string>>
     */
    private function combineAxis(array $axisLabels, array $series): array
    {
        return collect($axisLabels)
            ->map(function (string $label) use ($series): array {
                $row = ['label' => $label];

                foreach ($series as $key => $map) {
                    $row[$key] = (float) ($map[$label] ?? 0);
                }

                return $row;
            })
            ->all();
    }

    /**
     * Merge several monthly key→value maps onto a fixed month axis so they
     * can be plotted as multiple series on one chart. Missing months are 0.
     * Each row includes a numeric `month` field (1–12) for client-side filtering.
     * When $onlyMonth is set, only that month's row is returned.
     *
     * @deprecated Use combineAxis() with axisLabels() for new code.
     *
     * @param  array<int, string>  $months
     * @param  array<string, array<string, float|int>>  $series
     * @return array<int, array<string, float>>
     */
    private function combineMonths(array $months, ?int $onlyMonth, array $series): array
    {
        $rows = collect($months)
            ->map(function (string $month, int $index) use ($series): array {
                $row = ['label' => $month, 'month' => $index + 1];

                foreach ($series as $key => $map) {
                    $row[$key] = (float) ($map[$month] ?? 0);
                }

                return $row;
            })
            ->all();

        if ($onlyMonth) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['month'] === $onlyMonth));
        }

        return $rows;
    }
}

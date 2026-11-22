<?php

namespace App\Services;

use App\Enums\PlatformPermissionKey;
use App\Enums\SubPermissionKey;
use App\Models\Expense;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PlatformExpense;
use App\Models\Property;
use App\Models\SubscriptionPayment;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
    public function __construct(private RentArrearsService $arrears) {}

    /**
     * @param  array<int, array{id: int, properties: array<int, array{units_count: int}}>]|null  $access
     *         Precomputed PropertyAccessService result, when the caller already has it.
     */
    public function payload(User $user, ?array $access = null, ?int $year = null, ?int $month = null, ?int $propertyId = null): array
    {
        $access ??= app(PropertyAccessService::class)->organizationsWithProperties($user);
        $year ??= (int) date('Y');

        $scopedAccess = $this->scopeAccess($access, $propertyId);

        return [
            'available_years' => $this->availableYears(),
            'admin' => $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value)
                ? $this->adminStats($year, $month)
                : null,
            'organization' => $user->can(PlatformPermissionKey::ViewOrgMetrics->value)
                ? array_merge(
                    $this->organizationStats($scopedAccess, $year, $month, $user),
                    [
                        'property_options' => collect($access)
                            ->flatMap(fn (array $organization) => $organization['properties'])
                            ->map(fn (array $property) => ['id' => $property['id'], 'name' => $property['name']])
                            ->values()
                            ->all(),
                        'selected_property' => $propertyId,
                    ],
                )
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
            $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value) => 'platform',
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
     * Narrow the access set to one property. An unknown or inaccessible id
     * falls back to the full set rather than stranding the dashboard
     * empty — the id set is already access-filtered, so nothing leaks.
     *
     * @param  array<int, array{id: int, properties: array<int, array{id: int}}}>  $access
     * @return array<int, array{id: int, properties: array<int, array{id: int}}}>
     */
    private function scopeAccess(array $access, ?int $propertyId): array
    {
        if ($propertyId === null) {
            return $access;
        }

        $scoped = [];

        foreach ($access as $organization) {
            $properties = array_values(array_filter(
                $organization['properties'],
                fn (array $property): bool => $property['id'] === $propertyId,
            ));

            if ($properties !== []) {
                $organization['properties'] = $properties;
                $scoped[] = $organization;
            }
        }

        return $scoped === [] ? $access : $scoped;
    }

    /**
     * @param  array<int, array{id: int, properties: array<int, array{id: int, name: string}>}>  $access
     */
    private function organizationStats(array $access, int $year, ?int $month, User $user): array
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
            'collection' => $this->collectionStats($orgIds, $properties, $user),
            'attention' => $this->attentionItems($orgIds, $properties),
            'portfolio' => $this->portfolioDetails($orgIds, $properties, $year),
        ];
    }

    /**
     * Drill-down widgets for the landlord portfolio: unit grid, payment
     * timing, tax summary, P&L and land parcels. Everything reads the same
     * access-scoped ids as the collection stats above.
     *
     * @param  array<int, int>  $orgIds
     * @param  iterable<int, array{id: int, name: string}>  $properties
     * @return array{
     *     units_grid: list<array{property: string, units: list<array{id: int, name: string, status: string, overdue: bool, occupant: string|null, type: string|null, monthly_rent: string|null}>}>,
     *     timing: array{early: int, on_time: int, late: int},
     *     tax: list<array{label: string, total: string, properties: list<array{property: string, total: string}>}>,
     *     pnl: array{income_ytd: string, expenses_ytd: string, net_ytd: string, expenses_by_category: list<array{category: string, total: string}>},
     *     land: list<array{id: int, name: string, status: string, city: string|null, acreage: string|null, latitude: float|null, longitude: float|null}>,
     * }
     */
    private function portfolioDetails(array $orgIds, iterable $properties, int $year): array
    {
        $propertyIds = collect($properties)->pluck('id')->all();
        $propertyNames = collect($properties)->pluck('name', 'id')->all();

        if ($orgIds === [] || $propertyIds === []) {
            return [
                'units_grid' => [],
                'timing' => ['early' => 0, 'on_time' => 0, 'late' => 0],
                'tax' => [],
                'pnl' => ['income_ytd' => '0.00', 'expenses_ytd' => '0.00', 'net_ytd' => '0.00', 'expenses_by_category' => []],
                'land' => [],
            ];
        }

        // Unit grid: every unit with its current occupant, grouped by building.
        $unitsGrid = $this->unitsGrid($propertyIds, $propertyNames);

        // Payment timing against the billing period: early, within a
        // 7-day grace window, or late. Payments without a period carry no
        // timing signal and stay out of the counts.
        $timing = ['early' => 0, 'on_time' => 0, 'late' => 0];
        foreach (Payment::query()
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'received')
            ->whereNotNull('period_start')
            ->whereNotNull('paid_on')
            ->cursor(['paid_on', 'period_start']) as $payment) {
            if ($payment->paid_on->lessThan($payment->period_start)) {
                $timing['early']++;
            } elseif ($payment->paid_on->lessThanOrEqualTo($payment->period_start->copy()->addDays(7))) {
                $timing['on_time']++;
            } else {
                $timing['late']++;
            }
        }

        // Rental income summary: monthly received totals with a per-property
        // split. Gross figures for the accountant; tax treatment belongs to
        // one, not to this dashboard.
        $yearPayments = Payment::query()
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'received')
            ->whereYear('paid_on', $year)
            ->with('lease:id,property_id')
            ->get(['lease_id', 'amount', 'paid_on']);

        $tax = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthPayments = $yearPayments->filter(fn (Payment $payment) => (int) $payment->paid_on->format('n') === $m);
            $byProperty = $monthPayments
                ->groupBy(fn (Payment $payment) => $payment->lease?->property_id)
                ->map(fn ($group, $id) => [
                    'property' => $propertyNames[$id] ?? '',
                    'total' => number_format((float) $group->sum(fn (Payment $payment) => (float) $payment->amount), 2, '.', ''),
                ])
                ->filter(fn (array $row): bool => $row['property'] !== '')
                ->values()
                ->all();

            $tax[] = [
                'label' => Carbon::createFromDate($year, $m, 1)->format('M'),
                'total' => number_format((float) $monthPayments->sum(fn (Payment $payment) => (float) $payment->amount), 2, '.', ''),
                'properties' => $byProperty,
            ];
        }

        // P&L, year to date: received rent against property-attributed
        // expenses by category. Org-overhead expenses without a property
        // stay out and the card says so.
        $incomeYtd = (string) $yearPayments->sum(fn (Payment $payment) => (float) $payment->amount);

        $expenses = Expense::query()
            ->whereIn('organization_id', $orgIds)
            ->where('expenseable_type', Property::class)
            ->whereIn('expenseable_id', $propertyIds)
            ->whereYear('spent_on', $year)
            ->get(['category', 'amount']);

        $byCategory = $expenses
            ->groupBy(fn (Expense $expense) => (string) ($expense->category ?? 'Uncategorized'))
            ->map(fn ($group, string $category) => [
                'category' => ucfirst($category),
                'total' => number_format((float) $group->sum(fn (Expense $expense) => (float) $expense->amount), 2, '.', ''),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $expensesYtd = '0.00';
        foreach ($byCategory as $row) {
            $expensesYtd = bcadd($expensesYtd, $row['total'], 2);
        }

        $land = LandParcel::query()
            ->whereIn('organization_id', $orgIds)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'city', 'acreage', 'latitude', 'longitude'])
            ->map(fn (LandParcel $parcel): array => [
                'id' => $parcel->id,
                'name' => $parcel->name,
                'status' => $parcel->status,
                'city' => $parcel->city,
                'acreage' => $parcel->acreage !== null ? (string) $parcel->acreage : null,
                'latitude' => $parcel->latitude !== null ? (float) $parcel->latitude : null,
                'longitude' => $parcel->longitude !== null ? (float) $parcel->longitude : null,
            ])
            ->all();

        return [
            'units_grid' => $unitsGrid,
            'timing' => $timing,
            'tax' => $tax,
            'pnl' => [
                'income_ytd' => number_format((float) $incomeYtd, 2, '.', ''),
                'expenses_ytd' => $expensesYtd,
                'net_ytd' => bcsub(number_format((float) $incomeYtd, 2, '.', ''), $expensesYtd, 2),
                'expenses_by_category' => $byCategory,
            ],
            'land' => $land,
        ];
    }

    /**
     * Portfolio rent picture, scoped to accessible properties: what the
     * active leases ask for this month, what arrived, who owes what and
     * for how long, and which properties earn. Expected is the live rent
     * roll, not a forecast — prepayments can push collected past it, which
     * reads as over 100% rather than an error.
     *
     * @param  array<int, int>  $orgIds
     * @param  iterable<int, array{id: int, name: string}>  $properties
     * @return array{
     *     expected_mtd: string, collected_mtd: string, collection_rate: float|null,
     *     arrears_total: string, overdue_count: int,
     *     occupancy_rate: float|null, occupied: int, vacant: int, units_total: int,
     *     net_mtd: string,
     *     aging: list<array{bucket: string, total: string, count: int}>,
     *     defaulters: list<array{lease_id: int, occupant: string, unit: string|null, property: string, property_slug: string, outstanding: string, days_overdue: int, can_remind: bool}>,
     *     expiry: array{within_30: int, within_60: int, within_90: int},
     *     properties: list<array{property: string, income_mtd: string, expenses_mtd: string, net_mtd: string}>,
     * }
     */
    private function collectionStats(array $orgIds, iterable $properties, ?User $user): array
    {
        $propertyIds = collect($properties)->pluck('id')->all();
        $propertyNames = collect($properties)->pluck('name', 'id')->all();
        $monthStart = now()->startOfMonth()->toDateString();

        $organizations = Organization::query()->whereIn('id', $orgIds)->get()->keyBy('id');
        $remindable = [];
        foreach ($organizations as $organization) {
            $remindable[$organization->id] = $user !== null
                && $user->hasSubPermission($organization, SubPermissionKey::LeaseManage);
        }

        $leases = ($orgIds !== [] && $propertyIds !== [])
            ? Lease::query()
                ->whereIn('organization_id', $orgIds)
                ->whereIn('property_id', $propertyIds)
                ->where('status', 'active')
                ->with(['unit:id,name', 'person:id,first_name,last_name', 'property:id,name,slug'])
                ->get()
            : collect();

        $balances = $this->arrears->balancesFor($leases);

        $expected = '0.00';
        foreach ($leases as $lease) {
            $expected = bcadd($expected, number_format((float) ($lease->rent_amount ?? 0), 2, '.', ''), 2);
        }

        $leaseIds = $leases->pluck('id')->all();

        $collected = $leaseIds === [] ? '0.00' : (string) Payment::query()
            ->whereIn('lease_id', $leaseIds)
            ->where('status', 'received')
            ->whereDate('paid_on', '>=', $monthStart)
            ->sum('amount');

        $overdue = collect($balances)->filter(fn (array $balance): bool => $balance['overdue']);

        $buckets = [
            ['bucket' => '1–7 days', 'min' => 1, 'max' => 7, 'total' => '0.00', 'count' => 0],
            ['bucket' => '8–30 days', 'min' => 8, 'max' => 30, 'total' => '0.00', 'count' => 0],
            ['bucket' => '31–60 days', 'min' => 31, 'max' => 60, 'total' => '0.00', 'count' => 0],
            ['bucket' => '60+ days', 'min' => 61, 'max' => PHP_INT_MAX, 'total' => '0.00', 'count' => 0],
        ];

        foreach ($overdue as $balance) {
            foreach ($buckets as &$bucket) {
                if ($balance['days_overdue'] >= $bucket['min'] && $balance['days_overdue'] <= $bucket['max']) {
                    $bucket['total'] = bcadd($bucket['total'], $balance['outstanding'], 2);
                    $bucket['count']++;
                    break;
                }
            }
            unset($bucket);
        }

        $defaulters = $overdue
            ->sortByDesc(fn (array $balance): float => (float) $balance['outstanding'])
            ->take(5)
            ->map(fn (array $balance): array => [
                'lease_id' => $balance['lease']->id,
                'occupant' => trim(($balance['lease']->person?->first_name ?? '').' '.($balance['lease']->person?->last_name ?? '')),
                'unit' => $balance['lease']->unit?->name,
                'property' => $balance['lease']->property?->name ?? '',
                'property_slug' => $balance['lease']->property?->slug ?? '',
                'outstanding' => $balance['outstanding'],
                'days_overdue' => $balance['days_overdue'],
                'can_remind' => $remindable[$balance['lease']->organization_id] ?? false,
            ])
            ->values()
            ->all();

        $expiry = ['within_30' => 0, 'within_60' => 0, 'within_90' => 0];
        foreach ($leases as $lease) {
            if ($lease->ends_at === null) {
                continue;
            }

            $days = (int) now()->startOfDay()->diffInDays($lease->ends_at->startOfDay(), false);

            if ($days < 0) {
                continue;
            }

            if ($days <= 30) {
                $expiry['within_30']++;
            } elseif ($days <= 60) {
                $expiry['within_60']++;
            } elseif ($days <= 90) {
                $expiry['within_90']++;
            }
        }

        $incomeByProperty = $leaseIds === [] ? collect() : Payment::query()
            ->whereIn('lease_id', $leaseIds)
            ->where('status', 'received')
            ->whereDate('paid_on', '>=', $monthStart)
            ->with('lease:id,property_id')
            ->get(['lease_id', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->lease?->property_id);

        $expenseByProperty = $propertyIds === [] ? collect() : Expense::query()
            ->where('expenseable_type', Property::class)
            ->whereIn('expenseable_id', $propertyIds)
            ->whereDate('spent_on', '>=', $monthStart)
            ->get(['expenseable_id', 'amount'])
            ->groupBy('expenseable_id');

        $propertyRows = [];
        foreach ($propertyIds as $propertyId) {
            $income = (string) ($incomeByProperty->get($propertyId) ?? collect())->sum(fn ($payment) => (float) $payment->amount);
            $expenses = (string) ($expenseByProperty->get($propertyId) ?? collect())->sum(fn ($expense) => (float) $expense->amount);

            $propertyRows[] = [
                'property' => $propertyNames[$propertyId] ?? '',
                'income_mtd' => number_format((float) $income, 2, '.', ''),
                'expenses_mtd' => number_format((float) $expenses, 2, '.', ''),
                'net_mtd' => bcsub(number_format((float) $income, 2, '.', ''), number_format((float) $expenses, 2, '.', ''), 2),
            ];
        }

        $arrearsTotal = '0.00';
        foreach ($overdue as $balance) {
            $arrearsTotal = bcadd($arrearsTotal, $balance['outstanding'], 2);
        }

        $unitStatuses = $propertyIds === [] ? collect() : Unit::query()
            ->whereIn('property_id', $propertyIds)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $occupied = (int) ($unitStatuses->get('occupied') ?? 0);
        $vacant = (int) ($unitStatuses->get('vacant') ?? 0);
        $unitsTotal = (int) $unitStatuses->sum();

        $expensesMtd = '0.00';
        foreach ($propertyRows as $row) {
            $expensesMtd = bcadd($expensesMtd, $row['expenses_mtd'], 2);
        }

        return [
            'expected_mtd' => $expected,
            'collected_mtd' => number_format((float) $collected, 2, '.', ''),
            'collection_rate' => bccomp($expected, '0', 2) === 1
                ? round((float) $collected / (float) $expected * 100, 1)
                : null,
            'arrears_total' => $arrearsTotal,
            'overdue_count' => $overdue->count(),
            'occupancy_rate' => $unitsTotal > 0 ? round($occupied / $unitsTotal * 100, 1) : null,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'units_total' => $unitsTotal,
            'net_mtd' => bcsub(number_format((float) $collected, 2, '.', ''), $expensesMtd, 2),
            'aging' => collect($buckets)->map(fn (array $bucket) => [
                'bucket' => $bucket['bucket'],
                'total' => $bucket['total'],
                'count' => $bucket['count'],
            ])->all(),
            'defaulters' => $defaulters,
            'expiry' => $expiry,
            'properties' => $propertyRows,
        ];
    }

    /**
     * Landlord to-do list, plain text by the marketing rule: overdue
     * tenants past a week, leases expiring within 30 days. Stale urgent
     * maintenance already reports through flags, so it stays out.
     *
     * @param  array<int, int>  $orgIds
     * @param  iterable<int, array{id: int, name: string}>  $properties
     * @return list<array{severity: string, text: string}>
     */
    private function attentionItems(array $orgIds, iterable $properties): array
    {
        $propertyIds = collect($properties)->pluck('id')->all();

        if ($orgIds === [] || $propertyIds === []) {
            return [];
        }

        $items = [];

        $overdueWeek = Lease::query()
            ->whereIn('organization_id', $orgIds)
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->get();

        $weekOverdue = collect($this->arrears->balancesFor($overdueWeek))
            ->filter(fn (array $balance): bool => $balance['overdue'] && $balance['days_overdue'] > 7)
            ->count();

        if ($weekOverdue > 0) {
            $items[] = [
                'severity' => 'danger',
                'text' => "{$weekOverdue} tenant".($weekOverdue > 1 ? 's' : '').' more than 7 days overdue.',
            ];
        }

        $expiring = Lease::query()
            ->whereIn('organization_id', $orgIds)
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        if ($expiring > 0) {
            $items[] = [
                'severity' => 'warning',
                'text' => "{$expiring} lease".($expiring > 1 ? 's expire' : ' expires').' within 30 days — line up renewals.',
            ];
        }

        return $items;
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
     * maintenance, active leases, and monthly/daily activity (rent roll,
     * maintenance requests, new leases).
     *
     * @return array<string, mixed>
     */
    public function propertyStats(Property $property, ?int $year = null, ?int $month = null, ?User $user = null): array
    {
        $year ??= (int) date('Y');
        $labels = $this->axisLabels($year, $month);

        // Unit composition (always current, not affected by filters).
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

        // Available years: distinct years from leases + maintenance for this property.
        $availableYears = collect()
            ->concat(Lease::query()->where('property_id', $property->id)->pluck('starts_at'))
            ->concat(MaintenanceRequest::query()->where('property_id', $property->id)->pluck('created_at'))
            ->push(now())
            ->map(fn ($value) => (int) value($value)->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // --- Time-series aggregation ---

        $maintenanceSeries = [];
        $newLeasesSeries = [];
        $rentRollSeries = [];

        if ($month) {
            // Daily: aggregate by day number for the selected month.
            $startOfMonth = Carbon::createFromDate($year, $month, 1);
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            foreach (MaintenanceRequest::query()
                ->where('property_id', $property->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->cursor(['created_at']) as $request) {
                $key = $this->formatKey($request->created_at, $month);
                $maintenanceSeries[$key] = ($maintenanceSeries[$key] ?? 0) + 1;
            }

            foreach (Lease::query()
                ->where('property_id', $property->id)
                ->whereBetween('starts_at', [$startOfMonth, $endOfMonth])
                ->cursor(['starts_at']) as $lease) {
                $key = $this->formatKey($lease->starts_at, $month);
                $newLeasesSeries[$key] = ($newLeasesSeries[$key] ?? 0) + 1;
            }

            // Rent roll: sum of rent for leases active on each day.
            $activeLeasesForRent = Lease::query()
                ->where('property_id', $property->id)
                ->where(function ($q) use ($endOfMonth): void {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $endOfMonth->copy()->startOfDay());
                })
                ->where('starts_at', '<=', $endOfMonth)
                ->get(['starts_at', 'ends_at', 'rent_amount']);

            for ($day = 1; $day <= $startOfMonth->daysInMonth; $day++) {
                $date = $startOfMonth->copy()->day($day)->startOfDay();
                $roll = 0;

                foreach ($activeLeasesForRent as $lease) {
                    if ($lease->starts_at && $lease->starts_at->lte($date)
                        && (is_null($lease->ends_at) || $lease->ends_at->gte($date))) {
                        $roll += (float) $lease->rent_amount;
                    }
                }

                $rentRollSeries[(string) $day] = $roll;
            }
        } else {
            // Monthly: aggregate by month abbreviation.
            $startOfYear = Carbon::createFromDate($year, 1, 1);
            $endOfYear = $startOfYear->copy()->endOfYear();

            foreach (MaintenanceRequest::query()
                ->where('property_id', $property->id)
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->cursor(['created_at']) as $request) {
                $key = $this->formatKey($request->created_at, null);
                $maintenanceSeries[$key] = ($maintenanceSeries[$key] ?? 0) + 1;
            }

            foreach (Lease::query()
                ->where('property_id', $property->id)
                ->whereBetween('starts_at', [$startOfYear, $endOfYear])
                ->cursor(['starts_at']) as $lease) {
                $key = $this->formatKey($lease->starts_at, null);
                $newLeasesSeries[$key] = ($newLeasesSeries[$key] ?? 0) + 1;
            }

            // Rent roll: sum of rent for leases active during each month.
            $activeLeasesForRent = Lease::query()
                ->where('property_id', $property->id)
                ->where(function ($q) use ($endOfYear): void {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $endOfYear->copy()->startOfMonth());
                })
                ->where('starts_at', '<=', $endOfYear)
                ->get(['starts_at', 'ends_at', 'rent_amount']);

            for ($m = 1; $m <= 12; $m++) {
                $monthStart = Carbon::createFromDate($year, $m, 1)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $roll = 0;

                foreach ($activeLeasesForRent as $lease) {
                    if ($lease->starts_at && $lease->starts_at->lte($monthEnd)
                        && (is_null($lease->ends_at) || $lease->ends_at->gte($monthStart))) {
                        $roll += (float) $lease->rent_amount;
                    }
                }

                $rentRollSeries[$monthStart->format('M')] = $roll;
            }
        }

        $operations = $this->combineAxis($labels, [
            'rent_roll' => $rentRollSeries,
            'maintenance' => $maintenanceSeries,
            'new_leases' => $newLeasesSeries,
        ]);

        return [
            'available_years' => $availableYears,
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
            'collection' => $this->collectionStats(
                [$property->organization_id],
                [['id' => $property->id, 'name' => $property->name]],
                $user,
            ),
            'attention' => $this->attentionItems(
                [$property->organization_id],
                [['id' => $property->id]],
            ),
            'expiring_leases' => $this->expiringLeasesForProperty($property),
            'units_grid' => $this->unitsGrid([$property->id], [$property->id => $property->name]),
        ];
    }

    /**
     * Active leases ending within 60 days on one property, soonest first.
     *
     * @return list<array{unit: string|null, occupant: string|null, ends_at: string, days_left: int}>
     */
    private function expiringLeasesForProperty(Property $property): array
    {
        return Lease::query()
            ->where('property_id', $property->id)
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
     * Every unit with its current occupant and arrears flag, grouped by
     * building. Shared by the portfolio snapshot and the property page.
     *
     * @param  array<int, int>  $propertyIds
     * @param  array<int, string>  $propertyNames
     * @return list<array{property: string, units: list<array{id: int, name: string, status: string, overdue: bool, occupant: string|null, type: string|null, monthly_rent: string|null}>}>
     */
    private function unitsGrid(array $propertyIds, array $propertyNames): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $gridLeases = Lease::query()
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->whereNotNull('unit_id')
            ->with('person:id,first_name,last_name')
            ->get(['id', 'unit_id', 'person_id']);

        $leasesByUnit = $gridLeases->keyBy('unit_id');
        $overdueUnitIds = collect($this->arrears->balancesFor($gridLeases))
            ->filter(fn (array $balance): bool => $balance['overdue'])
            ->map(fn (array $balance) => $balance['lease']->unit_id)
            ->all();

        $unitsGrid = [];
        foreach ($propertyIds as $propertyId) {
            $units = Unit::query()
                ->where('property_id', $propertyId)
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'type', 'monthly_rent'])
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'status' => $unit->status,
                    'overdue' => in_array($unit->id, $overdueUnitIds, true),
                    'occupant' => ($lease = $leasesByUnit->get($unit->id)) !== null && $lease->person !== null
                        ? trim($lease->person->first_name.' '.$lease->person->last_name)
                        : null,
                    'type' => $unit->type,
                    'monthly_rent' => $unit->monthly_rent !== null ? (string) $unit->monthly_rent : null,
                ])
                ->all();

            $unitsGrid[] = [
                'property' => $propertyNames[$propertyId] ?? '',
                'units' => $units,
            ];
        }

        return $unitsGrid;
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
    private function formatKey(CarbonInterface $date, ?int $month): string
    {
        return $month === null ? $date->format('M') : $date->format('j');
    }

    /**
     * Merge several key→value maps onto a fixed x-axis so they can be
     * plotted as multiple series on one chart. Missing points are 0.
     *
     * @param  list<string>  $axisLabels
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

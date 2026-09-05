<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Builds the organization-level dashboard payload: aggregate stats plus a
 * per-property comparison for the assets the current user can actually
 * access inside the active tenancy.
 *
 * Owners see every property of the organization; staff only see the
 * properties delegated to them, so every aggregate and series below is
 * scoped to the accessible property IDs.
 */
class OrganizationDashboardService extends Service
{
    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, Organization $organization, ?int $year = null, ?int $month = null): array
    {
        $year ??= (int) date('Y');

        $access = app(PropertyAccessService::class)->organizationsWithProperties($user);
        $propertySummaries = collect(collect($access)->firstWhere('id', $organization->id)['properties'] ?? []);
        $propertyIds = $propertySummaries->pluck('id')->all();

        $properties = collect();
        if ($propertyIds !== []) {
            $properties = Property::query()
                ->whereIn('id', $propertyIds)
                ->with('units:id,property_id,status,monthly_rent')
                ->get(['id', 'name', 'slug', 'city', 'status']);
        }

        $openMaintenanceByProperty = $this->openMaintenanceByProperty($propertyIds);
        $activeLeasesByProperty = $this->activeLeasesByProperty($propertyIds);

        $perProperty = $properties
            ->map(fn (Property $property): array => $this->propertySnapshot(
                $property,
                $openMaintenanceByProperty,
                $activeLeasesByProperty,
            ))
            ->values()
            ->all();

        return [
            'organization' => $organization->only('id', 'name', 'slug'),
            'available_years' => $this->availableYears($organization, $propertyIds),
            'properties' => $perProperty,
            'properties_count' => count($perProperty),
            'total_units' => (int) array_sum(array_column($perProperty, 'total_units')),
            'occupied' => (int) array_sum(array_column($perProperty, 'occupied')),
            'vacant' => (int) array_sum(array_column($perProperty, 'vacant')),
            'in_maintenance' => (int) array_sum(array_column($perProperty, 'in_maintenance')),
            'open_maintenance' => (int) array_sum(array_column($perProperty, 'open_maintenance')),
            'active_leases' => (int) array_sum(array_column($perProperty, 'active_leases')),
            'rent_potential' => round(array_sum(array_column($perProperty, 'rent_potential')), 2),
            'occupancy_rate' => $this->occupancyRate($perProperty),
            'operations_monthly' => $this->operationsMonthly($organization, $propertyIds, $year, $month),
            'expiring_leases' => $this->expiringLeases($propertyIds),
            'flags' => $this->predictiveFlags($organization, $propertyIds),
        ];
    }

    /**
     * Per-property comparison snapshot: unit composition, rent potential and
     * the live maintenance/lease counts resolved in bulk by the caller.
     *
     * @param  array<int, int>  $openMaintenanceByProperty
     * @param  array<int, int>  $activeLeasesByProperty
     * @return array<string, mixed>
     */
    private function propertySnapshot(
        Property $property,
        array $openMaintenanceByProperty,
        array $activeLeasesByProperty,
    ): array {
        $units = $property->units;
        $occupied = $units->where('status', 'occupied')->count();
        $totalUnits = $units->count();

        return [
            'id' => $property->id,
            'name' => $property->name,
            'slug' => $property->slug,
            'city' => $property->city,
            'status' => $property->status,
            'total_units' => $totalUnits,
            'occupied' => $occupied,
            'vacant' => $units->where('status', 'vacant')->count(),
            'in_maintenance' => $units->where('status', 'maintenance')->count(),
            'occupancy_rate' => $totalUnits > 0 ? (int) round($occupied / $totalUnits * 100) : 0,
            'rent_potential' => round((float) $units->sum(fn ($unit) => (float) $unit->monthly_rent), 2),
            'open_maintenance' => (int) ($openMaintenanceByProperty[$property->id] ?? 0),
            'active_leases' => (int) ($activeLeasesByProperty[$property->id] ?? 0),
        ];
    }

    /**
     * @param  array<int, int>  $perProperty
     */
    private function occupancyRate(array $perProperty): int
    {
        $totalUnits = array_sum(array_column($perProperty, 'total_units'));

        if ($totalUnits === 0) {
            return 0;
        }

        return (int) round(array_sum(array_column($perProperty, 'occupied')) / $totalUnits * 100);
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<int, int>
     */
    private function openMaintenanceByProperty(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        return MaintenanceRequest::query()
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', ['opened', 'assigned', 'in_progress'])
            ->select('property_id', DB::raw('count(*) as c'))
            ->groupBy('property_id')
            ->pluck('c', 'property_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<int, int>
     */
    private function activeLeasesByProperty(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        return Lease::query()
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->select('property_id', DB::raw('count(*) as c'))
            ->groupBy('property_id')
            ->pluck('c', 'property_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Monthly (or daily when a month is selected) operations series across
     * every accessible property: expense totals, maintenance requests and
     * new leases.
     *
     * @param  list<int>  $propertyIds
     * @return array<int, array<string, float|int|string>>
     */
    private function operationsMonthly(Organization $organization, array $propertyIds, int $year, ?int $month): array
    {
        $labels = $this->axisLabels($year, $month);
        $expenseSeries = [];
        $maintenanceSeries = [];
        $newLeasesSeries = [];

        if ($propertyIds !== []) {
            $expenseQuery = Expense::query()
                ->where('organization_id', $organization->id)
                ->where('expenseable_type', Property::class)
                ->whereIn('expenseable_id', $propertyIds)
                ->whereYear('spent_on', $year);

            if ($month) {
                $expenseQuery->whereMonth('spent_on', $month);
            }

            foreach ($expenseQuery->cursor(['spent_on', 'amount']) as $expense) {
                $key = $this->formatKey($expense->spent_on, $month);
                $expenseSeries[$key] = ($expenseSeries[$key] ?? 0) + (float) $expense->amount;
            }

            $maintenanceQuery = MaintenanceRequest::query()
                ->whereIn('property_id', $propertyIds)
                ->whereYear('created_at', $year);

            if ($month) {
                $maintenanceQuery->whereMonth('created_at', $month);
            }

            foreach ($maintenanceQuery->cursor(['created_at']) as $request) {
                $key = $this->formatKey($request->created_at, $month);
                $maintenanceSeries[$key] = ($maintenanceSeries[$key] ?? 0) + 1;
            }

            $leaseQuery = Lease::query()
                ->whereIn('property_id', $propertyIds)
                ->whereYear('starts_at', $year);

            if ($month) {
                $leaseQuery->whereMonth('starts_at', $month);
            }

            foreach ($leaseQuery->cursor(['starts_at']) as $lease) {
                $key = $this->formatKey($lease->starts_at, $month);
                $newLeasesSeries[$key] = ($newLeasesSeries[$key] ?? 0) + 1;
            }
        }

        return $this->combineAxis($labels, [
            'expenses' => $expenseSeries,
            'maintenance' => $maintenanceSeries,
            'new_leases' => $newLeasesSeries,
        ]);
    }

    /**
     * Distinct years that appear in any organization data source plus the
     * current year, oldest first.
     *
     * @param  list<int>  $propertyIds
     * @return list<int>
     */
    private function availableYears(Organization $organization, array $propertyIds): array
    {
        return collect()
            ->concat($propertyIds !== [] ? Lease::query()->whereIn('property_id', $propertyIds)->pluck('starts_at') : [])
            ->concat($propertyIds !== [] ? MaintenanceRequest::query()->whereIn('property_id', $propertyIds)->pluck('created_at') : [])
            ->concat(Expense::query()->where('organization_id', $organization->id)->where('expenseable_type', Property::class)->whereIn('expenseable_id', $propertyIds)->pluck('spent_on'))
            ->push(now())
            ->map(fn ($value) => (int) value($value)->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Active leases ending within 60 days (max 5) across the accessible
     * properties, for the dashboard card.
     *
     * @param  list<int>  $propertyIds
     * @return array<int, array{name: string, occupant: string|null, ends_at: string|null, days_left: int}>
     */
    private function expiringLeases(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        return Lease::query()
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->with(['property:id,name', 'person:id,first_name,last_name'])
            ->orderBy('ends_at')
            ->limit(5)
            ->get()
            ->map(fn (Lease $lease): array => [
                'name' => $lease->property?->name ?? 'Unknown property',
                'occupant' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
                'ends_at' => $lease->ends_at?->toDateString(),
                'days_left' => now()->startOfDay()->diffInDays($lease->ends_at->startOfDay()),
            ])
            ->all();
    }

    /**
     * Deterministic predictive flags scoped to the accessible properties:
     * stale urgent maintenance and per-property expense outliers.
     *
     * @param  list<int>  $propertyIds
     * @return array<int, array{severity: string, message: string}>
     */
    private function predictiveFlags(Organization $organization, array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $flags = [];

        $staleUrgent = MaintenanceRequest::query()
            ->whereIn('property_id', $propertyIds)
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

        $recent = Expense::query()
            ->where('organization_id', $organization->id)
            ->where('expenseable_type', Property::class)
            ->whereIn('expenseable_id', $propertyIds)
            ->orderByDesc('spent_on')
            ->limit(50)
            ->get(['expenseable_id', 'category', 'amount', 'spent_on']);

        foreach ($recent as $expense) {
            $average = (float) Expense::query()
                ->where('organization_id', $organization->id)
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

    private function formatKey(CarbonInterface $date, ?int $month): string
    {
        return $month === null ? $date->format('M') : $date->format('j');
    }

    /**
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
}

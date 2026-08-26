<?php

namespace App\Services;

use App\Enums\PlatformPermissionKey;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\User;
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
    public function payload(User $user, ?array $access = null): array
    {
        $access ??= app(PropertyAccessService::class)->organizationsWithProperties($user);

        return [
            'admin' => $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value)
                ? $this->adminStats()
                : null,
            'organization' => $user->can(PlatformPermissionKey::ViewOrgMetrics->value)
                ? $this->organizationStats($access)
                : null,
            'searcher' => $user->can(PlatformPermissionKey::ViewSearcherMetrics->value)
                ? $this->personLeaseStats($user->person_id, 'searcher')
                : null,
            'occupant' => $user->can(PlatformPermissionKey::ViewOccupantMetrics->value)
                ? $this->personLeaseStats($user->person_id, 'occupant')
                : null,
            'defaultTab' => $this->defaultTab($user),
        ];
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

    private function adminStats(): array
    {
        $year = (int) date('Y');
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $plansBreakdown = DB::table('organizations')
            ->whereNotNull('plan_id')
            ->join('plans', 'plans.id', '=', 'organizations.plan_id')
            ->select('plans.name as plan', DB::raw('count(*) as count'))
            ->groupBy('plans.name')
            ->get()
            ->map(fn (object $row) => ['plan' => $row->plan, 'count' => (int) $row->count])
            ->all();

        return [
            'organizations_count' => Organization::count(),
            'subscribers_count' => Organization::whereNotNull('plan_id')->count(),
            'plans_count' => DB::table('plans')->count(),
            'plans_breakdown' => $plansBreakdown,
            'expenses' => $this->sampleSeries($months, 40000, 90000),
            'subscriptions_weekly' => $this->sampleSeries(
                collect(range(1, 12))->map(fn (int $i) => 'W'.$i)->all(),
                5,
                40,
            ),
            'subscriptions_monthly' => $this->sampleSeries($months, 20, 120),
            'subscriptions_yearly' => $this->sampleSeries(
                collect(range(4, 0))->map(fn (int $i) => (string) ($year - $i))->all(),
                100,
                600,
            ),
        ];
    }

    /**
     * @param  array<int, array{id: int, properties: array<int, array{units_count: int}>}>  $access
     */
    private function organizationStats(array $access): array
    {
        $orgIds = collect($access)->pluck('id')->all();
        $properties = collect($access)->flatMap(fn (array $organization) => $organization['properties']);

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return [
            'properties_count' => $properties->count(),
            'units_count' => $properties->sum('units_count'),
            'leases_count' => $orgIds ? Lease::whereIn('organization_id', $orgIds)->count() : 0,
            'active_leases' => $orgIds ? Lease::whereIn('organization_id', $orgIds)->active()->count() : 0,
            'expenses' => $this->sampleSeries($months, 10000, 40000),
            'maintenance_open' => 3,
            'maintenance_series' => $this->sampleSeries($months, 0, 25),
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
    private function personLeaseStats(?int $personId, string $kind): ?array
    {
        if (! $personId) {
            return [
                'leases_count' => 0,
                'active_leases' => 0,
                'ended_leases' => 0,
                'total_rent' => 0,
                'recent' => [],
            ];
        }

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

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

        return [
            'leases_count' => (int) ($totals->leases_count ?? 0),
            'active_leases' => (int) ($totals->active_leases ?? 0),
            'ended_leases' => (int) ($totals->ended_leases ?? 0),
            'total_rent' => (float) ($totals->total_rent ?? 0),
            'recent' => $recent,
        ] + ($kind === 'occupant' ? [
            'maintenance_open' => 2,
            'rent_trend' => $this->sampleSeries($months, 500, 1500),
        ] : []);
    }

    /**
     * Deterministic sample series so the placeholder graphs are stable
     * between requests (no flicker from random data).
     *
     * @param  array<int, string>  $labels
     * @return array<int, array{label: string, value: int, sample: bool}>
     */
    private function sampleSeries(array $labels, int $min, int $max): array
    {
        return collect($labels)
            ->map(function (string $label, int $index) use ($min, $max): array {
                $value = (int) ($min + (($max - $min) / 2) * (1 + sin(($index + 1) / 1.7)));

                return [
                    'label' => $label,
                    'value' => $value,
                    'sample' => true,
                ];
            })
            ->all();
    }
}

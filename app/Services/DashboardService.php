<?php

namespace App\Services;

use App\Enums\PlatformPermissionKey;
use App\Models\Lease;
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
    public function payload(User $user): array
    {
        return [
            'admin' => $user->can(PlatformPermissionKey::ViewAdvancedMetrics->value)
                ? $this->adminStats()
                : null,
            'organization' => $user->can(PlatformPermissionKey::ViewOrgMetrics->value)
                ? $this->organizationStats($user)
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

    private function organizationStats(User $user): array
    {
        $access = app(PropertyAccessService::class)->organizationsWithProperties($user);
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
        ];
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
        $leasesCount = (clone $base)->count();
        $activeLeases = (clone $base)->active()->count();
        $endedLeases = (clone $base)->ended()->count();
        $totalRent = (clone $base)->sum('rent_amount');

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
            'leases_count' => $leasesCount,
            'active_leases' => $activeLeases,
            'ended_leases' => $endedLeases,
            'total_rent' => (float) $totalRent,
            'recent' => $recent,
        ];

        if ($kind === 'occupant') {
            $result['maintenance_open'] = 2;
            $result['rent_trend'] = $this->sampleSeries($months, 500, 1500);
        }

        return $result;
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

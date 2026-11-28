<?php

namespace App\Actions\Metrics;

use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PlatformDailyMetric;
use App\Models\Property;
use App\Models\TenantDailyMetric;
use App\Models\Unit;
use App\Services\PlatformOverviewService;
use App\Services\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Captures one day of platform and per-organization metrics. Idempotent
 * per date: re-running overwrites today's rows instead of doubling them.
 *
 * Single-database tenancy means every tenant table carries organization_id
 * in the central database, so this loops organizations without switching
 * database connections. A failing organization is logged and skipped so
 * one bad row never blocks the platform rollup.
 */
class CapturePlatformSnapshot extends Service
{
    public function __construct(private PlatformOverviewService $overview) {}

    /**
     * @return array{date: string, organizations: int, skipped: int}
     */
    public function handle(?int $organizationId = null): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $organizations = Organization::query()
            ->when($organizationId !== null, fn ($query) => $query->whereKey($organizationId))
            ->orderBy('id')
            ->get();

        $skipped = 0;

        $totals = [
            'properties' => 0,
            'units' => 0,
            'rent' => '0',
            'maintenance' => 0,
        ];

        foreach ($organizations as $organization) {
            try {
                $row = $this->transaction(fn () => $this->captureOrganization($organization, $today));
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Platform snapshot skipped an organization.', [
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $totals['properties'] += $row->properties_count;
            $totals['units'] += $row->units_count;
            $totals['rent'] = bcadd($totals['rent'], (string) $row->rent_month_to_date, 2);
            $totals['maintenance'] += $row->maintenance_open_count;
        }

        $this->transaction(fn () => $this->capturePlatform($today, $totals));

        return ['date' => $today, 'organizations' => $organizations->count() - $skipped, 'skipped' => $skipped];
    }

    private function captureOrganization(Organization $organization, string $today): TenantDailyMetric
    {
        return tap(
            TenantDailyMetric::query()->where('organization_id', $organization->id)->whereDate('captured_on', $today)->first()
                ?? new TenantDailyMetric(['organization_id' => $organization->id, 'captured_on' => $today]),
            function (TenantDailyMetric $metric) use ($organization): void {
                $propertyIds = Property::query()->where('organization_id', $organization->id)->pluck('id');

                $monthStart = CarbonImmutable::now()->startOfMonth()->toDateString();
                $monthEnd = CarbonImmutable::now()->toDateString();

                $metric->fill([
                    'properties_count' => $propertyIds->count(),
                    'units_count' => $propertyIds->isEmpty() ? 0 : Unit::query()->whereIn('property_id', $propertyIds)->count(),
                    // People, not rows: one tenant may hold several tenancies.
                    'occupants_count' => Occupant::query()->where('organization_id', $organization->id)->distinct('person_id')->count('person_id'),
                    'active_leases_count' => Lease::query()->where('organization_id', $organization->id)->active()->count(),
                    'rent_month_to_date' => (string) Payment::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'received')
                        ->whereDate('paid_on', '>=', $monthStart)
                        ->whereDate('paid_on', '<=', $monthEnd)
                        ->sum('amount'),
                    'maintenance_open_count' => MaintenanceRequest::query()
                        ->where('organization_id', $organization->id)
                        ->whereIn('status', ['opened', 'assigned', 'in_progress'])
                        ->count(),
                    'uses_leases' => Lease::query()->where('organization_id', $organization->id)->exists(),
                    'uses_maintenance' => MaintenanceRequest::query()->where('organization_id', $organization->id)->exists(),
                    'uses_land' => LandParcel::query()->where('organization_id', $organization->id)->exists(),
                    'uses_mpesa_rent' => Payment::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'received')
                        ->where('method', 'mpesa')
                        ->exists(),
                ])->save();
            },
        );
    }

    /**
     * @param  array{properties: int, units: int, rent: string, maintenance: int}  $totals
     */
    private function capturePlatform(string $today, array $totals): PlatformDailyMetric
    {
        $payload = $this->overview->payload();
        $kpis = $payload['kpis'];

        return tap(
            PlatformDailyMetric::query()->whereDate('captured_on', $today)->first()
                ?? new PlatformDailyMetric(['captured_on' => $today]),
            function (PlatformDailyMetric $metric) use ($kpis, $totals): void {
                $metric->fill([
                    'mrr' => $kpis['mrr']['value'],
                    'paying_organizations' => $kpis['mrr']['paying_organizations'],
                    'active_organizations' => $kpis['active_organizations']['value'],
                    'trials' => $kpis['active_organizations']['on_trial'],
                    'past_due' => $kpis['past_due']['value'],
                    'signups_month_to_date' => $kpis['signups_this_month']['value'],
                    'income_month_to_date' => $kpis['collected_this_month']['value'],
                    'properties_total' => $totals['properties'],
                    'units_total' => $totals['units'],
                    'rent_month_to_date' => $totals['rent'],
                    'maintenance_open' => $totals['maintenance'],
                ])->save();
            },
        );
    }
}

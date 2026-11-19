<?php

use App\Actions\Metrics\CapturePlatformSnapshot;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformDailyMetric;
use App\Models\Property;
use App\Models\TenantDailyMetric;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function snapshotGraph(): Organization
{
    $plan = Plan::factory()->create(['price' => 7500]);

    $organization = Organization::factory()->create([
        'plan_id' => $plan->id,
        'plan_confirmed_at' => now()->subDays(40),
        'trial_ends_at' => now()->subDays(10),
        'subscription_ends_at' => now()->addDays(20),
    ]);

    $property = Property::factory()->create(['organization_id' => $organization->id]);
    Unit::factory()->create(['property_id' => $property->id]);
    $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);

    $lease = Lease::factory()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => null,
    ]);

    Payment::factory()->create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'occupant_id' => $occupant->id,
        'amount' => 25000,
        'status' => 'received',
        'method' => 'mpesa',
        'paid_on' => now()->toDateString(),
    ]);

    MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'title' => 'Leaking tap',
        'description' => 'Kitchen tap drips.',
        'status' => 'opened',
        'priority' => 'high',
        'raised_by' => User::factory()->create()->id,
    ]);

    LandParcel::factory()->create(['organization_id' => $organization->id]);

    return $organization;
}

test('capture records per-organization counters and feature flags', function () {
    $organization = snapshotGraph();

    $summary = app(CapturePlatformSnapshot::class)->handle();

    expect($summary['organizations'])->toBe(1)
        ->and($summary['skipped'])->toBe(0);

    $row = TenantDailyMetric::where('organization_id', $organization->id)->firstOrFail();

    expect($row->properties_count)->toBe(1)
        ->and($row->units_count)->toBe(1)
        ->and($row->occupants_count)->toBe(1)
        ->and($row->active_leases_count)->toBe(1)
        ->and((float) $row->rent_month_to_date)->toBe(25000.0)
        ->and($row->maintenance_open_count)->toBe(1)
        ->and($row->uses_leases)->toBeTrue()
        ->and($row->uses_maintenance)->toBeTrue()
        ->and($row->uses_land)->toBeTrue()
        ->and($row->uses_mpesa_rent)->toBeTrue();
});

test('capture rolls platform totals and stays idempotent', function () {
    snapshotGraph();
    Organization::factory()->create();

    $capture = app(CapturePlatformSnapshot::class);
    $capture->handle();

    expect(PlatformDailyMetric::count())->toBe(1)
        ->and(TenantDailyMetric::count())->toBe(2);

    $platform = PlatformDailyMetric::firstOrFail();

    expect((float) $platform->mrr)->toBe(7500.0)
        ->and($platform->units_total)->toBe(1)
        ->and((float) $platform->rent_month_to_date)->toBe(25000.0);

    $capture->handle();

    expect(PlatformDailyMetric::count())->toBe(1)
        ->and(TenantDailyMetric::count())->toBe(2);
});

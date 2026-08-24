<?php

use App\Enums\PlatformRole;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function leaseOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

test('assigning an occupant to a unit creates an active lease with cost data', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $org = leaseOrg($owner);
    $property = $org->properties()->create(['name' => 'Sunset', 'slug' => 'sunset', 'status' => 'active', 'created_by' => $owner->id]);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'type' => '1BR', 'created_by' => $owner->id]);

    $occupant = app(OccupantService::class)->create($org, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => [
            'starts_at' => '2026-01-01',
            'rent_amount' => 25000,
            'rent_frequency' => 'monthly',
            'deposit' => 25000,
            'currency' => 'KES',
        ],
    ]);

    $lease = Lease::where('person_id', $occupant->person_id)->where('unit_id', $unit->id)->first();

    expect($lease)->not->toBeNull()
        ->and($lease->status)->toBe('active')
        ->and($lease->ends_at)->toBeNull()
        ->and($lease->rent_amount)->toBe(25000.0)
        ->and($lease->durationInDays())->toBeGreaterThan(100)
        ->and($lease->totalCost())->toBeGreaterThan(25000.0);
});

test('moving an occupant out of a unit ends that lease but keeps active ones, and strips the occupant role only when none remain', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $org = leaseOrg($owner);
    $property = $org->properties()->create(['name' => 'Sunset', 'slug' => 'sunset', 'status' => 'active', 'created_by' => $owner->id]);
    $unitA = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'type' => '1BR', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'occupied', 'type' => '1BR', 'created_by' => $owner->id]);

    $occupant = app(OccupantService::class)->create($org, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unitA->id, $unitB->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    // Move out of A only.
    app(OccupantService::class)->update($occupant, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unitB->id],
    ]);

    expect(Lease::where('unit_id', $unitA->id)->where('status', 'active')->exists())->toBeFalse()
        ->and(Lease::where('unit_id', $unitB->id)->where('status', 'active')->exists())->toBeTrue();
    // Still has an active lease (B), so the occupant role stays.
    expect(User::where('email', 'jane@acme.test')->first()->hasRole(PlatformRole::Occupant->value))->toBeTrue();

    // Move out of B as well.
    app(OccupantService::class)->update($occupant, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [],
    ]);

    expect(Lease::where('person_id', $occupant->person_id)->where('status', 'active')->exists())->toBeFalse();
    expect(User::where('email', 'jane@acme.test')->first()->hasRole(PlatformRole::Occupant->value))->toBeFalse();
});

test('lease visibility: own / org staff / admin, but not other users', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $org = leaseOrg($owner);
    $property = $org->properties()->create(['name' => 'Sunset', 'slug' => 'sunset', 'status' => 'active', 'created_by' => $owner->id]);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'type' => '1BR', 'created_by' => $owner->id]);

    $occupant = app(OccupantService::class)->create($org, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    $lease = Lease::where('person_id', $occupant->person_id)->first();
    $jane = User::where('email', 'jane@acme.test')->first();

    expect($jane->can('view', $lease))->toBeTrue();

    $stranger = User::factory()->create(['onboarded_at' => now(), 'person_id' => Person::factory()->create()->id]);
    expect($stranger->can('view', $lease))->toBeFalse();

    expect($owner->can('view', $lease))->toBeTrue();

    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);
    expect($admin->can('view', $lease))->toBeTrue();
});

test('the moveOut action ends leases, strips the occupant role, and keeps the record', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $org = leaseOrg($owner);
    $property = $org->properties()->create(['name' => 'Sunset', 'slug' => 'sunset', 'status' => 'active', 'created_by' => $owner->id]);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'type' => '1BR', 'created_by' => $owner->id]);

    $occupant = app(OccupantService::class)->create($org, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    app(OccupantService::class)->moveOut($occupant, $property, $owner);

    expect(Lease::where('person_id', $occupant->person_id)->where('status', 'active')->exists())->toBeFalse()
        ->and(User::where('email', 'jane@acme.test')->first()->hasRole(PlatformRole::Occupant->value))->toBeFalse()
        ->and($occupant->fresh()->status)->toBe('moved_out')
        ->and(Occupant::withTrashed()->where('id', $occupant->id)->exists())->toBeTrue();
});

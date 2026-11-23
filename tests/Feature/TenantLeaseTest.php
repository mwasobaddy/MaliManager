<?php

use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function leaseOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function leaseProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function leaseUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.lease.test'],
        ['domain' => $organization->slug.'.lease.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('owners see the property leases index with active and ended leases', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    $unitA = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'A2', 'status' => 'vacant', 'created_by' => $owner->id]);

    Lease::create([
        'person_id' => Person::create(['first_name' => 'Jane', 'email' => 'jane@x.test', 'status' => 'active'])->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unitA->id,
        'starts_at' => '2026-01-01',
        'status' => 'active',
    ]);
    Lease::create([
        'person_id' => Person::create(['first_name' => 'Mark', 'email' => 'mark@x.test', 'status' => 'active'])->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unitB->id,
        'starts_at' => '2025-01-01',
        'ends_at' => '2025-12-01',
        'status' => 'ended',
    ]);

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/leases/index')
            ->where('leases.data', fn ($rows) => count($rows) === 2));

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?status=active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data', fn ($rows) => count($rows) === 1 && $rows[0]['status'] === 'active'));
});

test('ending a lease requires the current password and stamps the end date', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
    ]);

    $lease = Lease::where('unit_id', $unit->id)->where('status', 'active')->first();

    // Wrong password is rejected.
    $this->actingAs($owner)
        ->post(leaseUrl($organization, "/sunset-heights/leases/{$lease->id}/end"), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

    expect($lease->refresh()->isActive())->toBeTrue();

    // Correct password ends it.
    $this->actingAs($owner)
        ->post(leaseUrl($organization, "/sunset-heights/leases/{$lease->id}/end"), [
            'password' => 'password',
        ])->assertRedirect();

    expect($lease->refresh()->status)->toBe('ended')
        ->and($lease->ends_at)->not->toBeNull()
        // The occupant role is stripped once no active leases remain.
        ->and($occupant->person->users()->first()?->hasRole('occupant'))->toBeFalse();
});

function makeTenantLease(Organization $organization, User $owner, Property $property, string $unitName = 'A1', array $overrides = []): Lease
{
    $defaults = [
        'person_id' => Person::create(['first_name' => 'Jane', 'email' => 'jane@x.test', 'status' => 'active'])->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $property->units()->create(['name' => $unitName, 'status' => 'occupied', 'created_by' => $owner->id])->id,
        'starts_at' => '2026-01-01',
        'rent_amount' => 25000,
        'currency' => 'KES',
        'status' => 'active',
    ];

    return Lease::create([...$defaults, ...$overrides]);
}

test('search narrows leases by occupant name or unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    makeTenantLease($organization, $owner, $property, 'A1', []);
    makeTenantLease($organization, $owner, $property, 'B2', [
        'person_id' => Person::create(['first_name' => 'Mark', 'email' => 'mark@x.test', 'status' => 'active'])->id,
    ]);

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?search=Mark'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data', fn ($rows) => count($rows) === 1 && $rows[0]['unit_name'] === 'B2'));

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?search=A1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data', fn ($rows) => count($rows) === 1 && $rows[0]['occupant_name'] === 'Jane'));
});

test('leases sort by rent, unit and start date', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    makeTenantLease($organization, $owner, $property, 'A1', ['rent_amount' => 30000, 'starts_at' => '2026-03-01']);
    makeTenantLease($organization, $owner, $property, 'B2', [
        'rent_amount' => 20000,
        'starts_at' => '2026-01-01',
    ]);

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?sort=rent&direction=asc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data.0.rent_amount', 20000)
            ->where('leases.data.1.rent_amount', 30000));

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?sort=unit&direction=asc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data.0.unit_name', 'A1')
            ->where('leases.data.1.unit_name', 'B2'));
});

test('the view sheet resolves the focused lease with its balance', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    $lease = makeTenantLease($organization, $owner, $property);

    $this->actingAs($owner)
        ->get(leaseUrl($organization, "/sunset-heights/leases?mode=view&lease={$lease->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/leases/index')
            ->where('focusedLease.id', $lease->id)
            ->where('focusedLease.unit_name', 'A1')
            ->where('focusedLease.occupant_name', 'Jane'));

    $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases?mode=view&lease=999999'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedLease', null));
});

test('export downloads the filtered set as csv', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    makeTenantLease($organization, $owner, $property, 'A1', []);
    makeTenantLease($organization, $owner, $property, 'B2', [
        'status' => 'ended',
        'ends_at' => '2025-12-01',
    ]);

    $response = $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases/export?status=active'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines[0])->toContain('Unit')
        ->and(count($lines))->toBe(2)
        ->and($lines[1])->toContain('A1');
});

test('export honors a column subset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = leaseOrganization($owner);
    $property = leaseProperty($organization, $owner);

    makeTenantLease($organization, $owner, $property);

    $response = $this->actingAs($owner)
        ->get(leaseUrl($organization, '/sunset-heights/leases/export?columns[]=Unit&columns[]=Status'));

    $response->assertOk();
    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines[0])->toBe('Unit,Status');
});
